<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Dom;
use Nette\InvalidArgumentException;
use function count, in_array, strlen;


/**
 * Applies CSS rules as inline styles to HTML elements using DOM CSS selectors.
 * Requires PHP 8.4+ for Dom\HTMLDocument support.
 */
class CssInliner
{
	private const Patterns = [
		self::T_Comment => '/\*[^*]*\*+(?:[^/*][^*]*\*+)*/',
		self::T_Whitespace => '[\s]+',
		self::T_String => '"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\]++|\\\.)*+\'',
		self::T_Url => 'url\(\s*(?:"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\]++|\\\.)*+\'|[^)"\']*?)\s*\)',
		self::T_Escape => '\x5c[^\n\r\f]',
		self::T_AtIdent => '@-?[a-zA-Z_][\w-]*',
		self::T_Hash => '\#[\w-]+',
		self::T_Number => '[+-]?(?:\d+\.?\d*|\.\d+)(?:%|[a-zA-Z]+)?',
		self::T_Ident => '--[\w-]+|-?[a-zA-Z_][\w-]*',
		self::T_Char => '[{}();:,.\[\]>+\~=*!/^$|&\-<]',
	];

	private const
		T_Comment = 1,
		T_Whitespace = 2,
		T_String = 3,
		T_Url = 4,
		T_Escape = 5,
		T_AtIdent = 6,
		T_Hash = 7,
		T_Number = 8,
		T_Ident = 9,
		T_Char = 10;

	// CSS → [HTML attribute, type, allowed elements]. align/valign excluded: different semantics than CSS.
	private const HtmlAttributes = [
		'background-color' => ['bgcolor', 'string', ['table', 'td', 'th', 'body', 'tr']],
		'width'            => ['width', 'int', ['table', 'td', 'th', 'img']],
		'height'           => ['height', 'int', ['table', 'td', 'th', 'img']],
		'border-spacing'   => ['cellspacing', 'int', ['table']],
	];

	/** @var list<array{string, list<array{string, string}>}> */
	private array $rules = [];


	/**
	 * Adds CSS stylesheet rules to be applied during inlining.
	 */
	public function addCss(string $css): static
	{
		$this->rules = [...$this->rules, ...self::parseStylesheet($css)];
		return $this;
	}


	/**
	 * Returns the collected rules as [selector, declarations] pairs, with the last value of each property.
	 * @return list<array{string, array<string, string>}>
	 */
	public function getRules(): array
	{
		return array_map(fn(array $rule) => [$rule[0], array_column($rule[1], 1, 0)], $this->rules);
	}


	/**
	 * Applies all added CSS rules as inline styles to the given HTML.
	 * Also extracts and inlines rules from <style> tags (which are preserved).
	 *
	 * Declarations compete exactly as they would in a browser: !important wins over normal, an
	 * existing inline style wins over any selector, and among equals the more specific selector
	 * wins, ties going to the later declaration. All of them are written out ordered by rank, the winner
	 * last, so a client that does not understand a value falls back to the previous one.
	 */
	public function inline(string $html): string
	{
		$doc = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

		$rules = [];
		foreach ($doc->querySelectorAll('style') as $styleEl) {
			$rules = [...$rules, ...self::parseStylesheet($styleEl->textContent ?? '')];
		}

		$rules = [...$rules, ...$this->rules];

		/** @var array<int, Dom\Element> */
		$elements = [];
		/** @var array<int, array<string, list<array{list<int>, int, string}>>>  element => property => candidates */
		$candidates = [];
		$order = 0;

		foreach ($rules as [$selector, $declarations]) {
			// each comma-separated part carries its own specificity, and one part the DOM engine
			// rejects must not take the others down with it
			foreach (self::splitSelector($selector) as $part) {
				try {
					$matched = $doc->querySelectorAll($part);
				} catch (\DOMException) {
					continue; // a selector the DOM engine does not know, e.g. ::marker or :hover
				}

				if (!count($matched)) {
					continue;
				}

				$specificity = self::computeSpecificity($part);
				foreach ($matched as $element) {
					$id = spl_object_id($element);
					$elements[$id] = $element;
					$candidates[$id] ??= [];
					foreach ($declarations as $k => [$property, $value]) {
						self::addCandidate($candidates[$id], $property, $value, $specificity, $order + $k);
					}
				}
			}

			$order += count($declarations);
		}

		foreach ($candidates as $id => $properties) {
			$element = $elements[$id];

			$raw = null;
			$existing = $element->getAttribute('style') ?? '';
			if (trim($existing) !== '') {
				try {
					foreach (self::parseDeclarations($existing) as $k => [$property, $value]) {
						// an inline declaration outranks every selector, but still loses to !important
						self::addCandidate($properties, $property, $value, [0, 0, 0], $order + $k, inline: true);
					}
				} catch (InvalidArgumentException) {
					$raw = $existing; // not parseable, so keep it verbatim at the end where it wins
				}
			}

			$declarations = self::cascade($properties);
			$winners = array_column($declarations, 1, 0);

			$css = self::buildDeclarations($declarations);
			$element->setAttribute('style', $css . ($raw === null ? '' : '; ' . $raw));

			// Generate HTML attributes for email client compatibility (Outlook)
			$tag = strtolower($element->tagName);
			foreach (self::HtmlAttributes as $cssProp => [$attr, $type, $tags]) {
				$value = isset($winners[$cssProp]) && in_array($tag, $tags, true)
					? self::attributeValue($winners[$cssProp], $type)
					: null;

				if ($value !== null) {
					$element->setAttribute($attr, $value);
				}
			}
		}

		return $doc->saveHtml();
	}


	/**
	 * Adds a declaration competing for a property on an element. Its rank is the importance flag, then
	 * the inline-style tier, the specificity triple, and the source order, so two ranks are never equal.
	 * @param  array<string, list<array{list<int>, int, string}>>  $candidates
	 * @param  array{int, int, int}  $specificity
	 */
	private static function addCandidate(
		array &$candidates,
		string $property,
		string $value,
		array $specificity,
		int $order,
		bool $inline = false,
	): void
	{
		$candidates[$property][] = [[self::isImportant($value), (int) $inline, ...$specificity, $order], $order, $value];
	}


	/**
	 * Orders the candidates of each property by rank, the winner last, dropping those that a higher-ranked
	 * candidate repeats. Properties follow the source order of their winners, so the output reads naturally.
	 * @param  array<string, list<array{list<int>, int, string}>>  $candidates
	 * @return list<array{string, string}>
	 */
	private static function cascade(array $candidates): array
	{
		$groups = [];
		foreach ($candidates as $property => $list) {
			usort($list, fn(array $a, array $b) => $b[0] <=> $a[0]);
			$groups[$list[0][1]] = [$property, array_reverse(array_unique(array_column($list, 2)))];
		}

		ksort($groups);
		$declarations = [];
		foreach ($groups as [$property, $values]) {
			foreach ($values as $value) {
				$declarations[] = [$property, $value];
			}
		}

		return $declarations;
	}


	/**
	 * Renders a declaration value as an HTML attribute: importance means nothing there, and a length has
	 * to lose its unit (width: 600px → width="600"), a percentage keeping it (width: 50% → width="50%").
	 * Returns null for anything an attribute cannot express -- 'auto', 'inherit', calc() -- because
	 * casting those to a number would silently emit width="0" and collapse the element in Outlook.
	 * @param  'string'|'int'  $type
	 */
	private static function attributeValue(string $value, string $type): ?string
	{
		$value = self::stripImportant($value);
		return match ($type) {
			'string' => $value,
			'int' => preg_match('~^([+-]?\d+(?:\.\d+)?)(%|[a-z]+)?$~i', $value, $m)
				? $m[1] . (($m[2] ?? '') === '%' ? '%' : '')
				: null,
		};
	}


	private static function isImportant(string $value): int
	{
		return preg_match('~!\s*important\s*$~i', $value);
	}


	private static function stripImportant(string $value): string
	{
		return trim((string) preg_replace('~!\s*important\s*$~i', '', $value));
	}


	/**
	 * Parses CSS stylesheet text into a list of selector + declarations pairs.
	 * @return list<array{string, list<array{string, string}>}>
	 */
	private static function parseStylesheet(string $css): array
	{
		$tokens = self::tokenize($css);
		$rules = [];
		$i = 0;
		self::parseBlock($tokens, $i, '', $rules);
		return $rules;
	}


	/**
	 * Parses a bare declaration list, as found in a style="" attribute, by wrapping it into a block.
	 * A brace means the attribute is not a plain declaration list: it would smuggle rules of its own
	 * into the wrapping block.
	 * @return list<array{string, string}>
	 * @throws InvalidArgumentException
	 */
	private static function parseDeclarations(string $css): array
	{
		foreach (self::tokenize($css) as [$type]) {
			if ($type === '{' || $type === '}') {
				throw new InvalidArgumentException('Unexpected brace in a declaration list.');
			}
		}

		return self::parseStylesheet('x{' . $css . '}')[0][1] ?? [];
	}


	/**
	 * Splits a selector list into its individual selectors. Commas nested inside brackets or
	 * parentheses -- [data-x="a,b"], :not(a, b) -- do not separate anything.
	 * @return list<string>
	 */
	private static function splitSelector(string $selector): array
	{
		$parts = [];
		$current = '';
		$depth = 0;

		foreach (self::tokenize($selector) as [$type, $text]) {
			if ($type === '(' || $type === '[') {
				$depth++;
			} elseif ($type === ')' || $type === ']') {
				$depth--;
			} elseif ($type === ',' && $depth === 0) {
				$parts[] = trim($current);
				$current = '';
				continue;
			}

			$current .= $text;
		}

		$parts[] = trim($current);
		return array_values(array_filter($parts, fn($part) => $part !== ''));
	}


	/**
	 * Computes the specificity of a single selector as (ids, classes, types), per CSS Selectors Level 4.
	 * :where() contributes nothing; the arguments of :not(), :is() and :has() are counted in place of
	 * the pseudo-class itself, which is the specification's rule of taking their most specific argument
	 * (this sums them instead, which for the CSS an email carries amounts to the same thing).
	 * @return array{int, int, int}
	 */
	private static function computeSpecificity(string $selector): array
	{
		$tokens = self::tokenize($selector);
		$count = count($tokens);
		$ids = $classes = $types = 0;

		for ($i = 0; $i < $count; $i++) {
			$type = $tokens[$i][0];

			if ($type === self::T_Hash) { // #id
				$ids++;

			} elseif ($type === '[') { // [attr], counts once no matter what is inside
				$classes++;
				self::skipBalanced($tokens, $i, '[', ']');

			} elseif ($type === '.') { // .class
				$classes++;
				$i++; // the name that follows is not a type selector

			} elseif ($type === ':') {
				if (($tokens[$i + 1][0] ?? null) === ':') { // ::pseudo-element
					$types++;
					$i += 2;
					continue;
				}

				$name = strtolower($tokens[$i + 1][1] ?? '');
				$i++;

				if ($name === 'where') { // zero specificity, and so are its arguments
					self::skipGroup($tokens, $i);

				} elseif (in_array($name, ['not', 'is', 'has'], true)) {
					// these contribute nothing themselves; their arguments are counted where they stand

				} else {
					$classes++; // an ordinary pseudo-class such as :hover or :nth-child()
					// its argument is a keyword or an An+B expression (odd, -n+3), not a selector, so
					// counting the idents inside would inflate the type count
					self::skipGroup($tokens, $i);
				}

			} elseif ($type === self::T_Ident) { // a type selector; * and combinators add nothing
				$types++;
			}
		}

		return [$ids, $classes, $types];
	}


	/**
	 * Skips a parenthesized group, if the next token opens one, leaving $i on its closing paren.
	 * @param  list<array{int|string, string}>  $tokens
	 */
	private static function skipGroup(array $tokens, int &$i): void
	{
		if (($tokens[$i + 1][0] ?? null) === '(') {
			$i++;
			self::skipBalanced($tokens, $i, '(', ')');
		}
	}


	/**
	 * Skips tokens up to the matching closing bracket: enters on the opening token, leaves on the closing one.
	 * @param  list<array{int|string, string}>  $tokens
	 */
	private static function skipBalanced(array $tokens, int &$i, string $open, string $close): void
	{
		$count = count($tokens);
		for ($depth = 1; $depth > 0 && ++$i < $count;) {
			$depth += (int) ($tokens[$i][0] === $open) - (int) ($tokens[$i][0] === $close);
		}
	}


	/**
	 * Parses a CSS block, collecting declarations and recursing into nested rules.
	 * @param  list<array{int|string, string}>  $tokens
	 * @param  list<array{string, list<array{string, string}>}>  &$rules
	 */
	private static function parseBlock(array $tokens, int &$i, string $parentSelector, array &$rules): void
	{
		$count = count($tokens);
		/** @var list<array{string, string}> */
		$declarations = [];

		while ($i < $count && $tokens[$i][0] !== '}') {
			if (isset([self::T_Whitespace => 1, self::T_Comment => 1, ';' => 1][$tokens[$i][0]])) {
				$i++;
				continue;
			}

			// Accumulate tokens until '{', ';', or '}', tracking first ':'
			$part = '';
			$colonPos = null;
			while ($i < $count && !isset(['{' => 1, '}' => 1, ';' => 1][$tokens[$i][0]])) {
				if ($tokens[$i][0] !== self::T_Comment) {
					if ($colonPos === null && $tokens[$i][0] === ':') {
						$colonPos = strlen($part);
					}

					$part .= $tokens[$i][1];
				}

				$i++;
			}

			if ($i >= $count) {
				break;
			}

			$part = trim($part);
			if ($tokens[$i][0] === '{') {
				$i++; // skip '{'

				if ($part !== '' && $part[0] === '@') {
					// Skip @-rule block respecting nesting
					$depth = 1;
					while ($i < $count && $depth > 0) {
						if ($tokens[$i][0] === '{') {
							$depth++;
						} elseif ($tokens[$i][0] === '}') {
							$depth--;
						}

						$i++;
					}
				} else {
					// Emit parent's declarations before nested rules
					if ($parentSelector !== '' && $declarations !== []) {
						$rules[] = [$parentSelector, $declarations];
						$declarations = [];
					}

					$fullSelector = match (true) {
						$parentSelector === '' => $part,
						str_contains($part, '&') => str_replace('&', $parentSelector, $part),
						default => $parentSelector . ' ' . $part,
					};
					self::parseBlock($tokens, $i, $fullSelector, $rules);
					if ($i < $count) {
						$i++; // skip '}'
					}
				}
			} else {
				// Declaration: split on tracked ':'
				if ($colonPos !== null) {
					$property = trim(substr($part, 0, $colonPos));
					$value = trim(substr($part, $colonPos + 1));

					// property names are case-insensitive, so COLOR and color must not end up as two
					// separate declarations, one of them overriding the winner in the client. Custom
					// properties are the exception: --Gap and --gap really are different.
					if (!str_starts_with($property, '--')) {
						$property = strtolower($property);
					}

					if ($property !== '' && $value !== '') {
						$declarations[] = [$property, $value];
					}
				}

				if ($i < $count && $tokens[$i][0] === ';') {
					$i++;
				}
			}
		}

		if ($parentSelector !== '' && $declarations !== []) {
			$rules[] = [$parentSelector, $declarations];
		}
	}


	/**
	 * Tokenizes a CSS string into a flat array of [type, text] pairs.
	 * @return list<array{int|string, string}>
	 */
	private static function tokenize(string $input): array
	{
		if ($input === '') {
			return [];
		}

		$re = '~(' . implode(')|(', self::Patterns) . ')~Asu';
		preg_match_all($re, $input, $matches, PREG_SET_ORDER);

		$types = array_keys(self::Patterns);
		$tokens = [];
		$len = 0;

		foreach ($matches as $match) {
			$type = $types[count($match) - 2];
			$text = $match[0];
			$tokens[] = [$type === self::T_Char ? $text : $type, $text];
			$len += strlen($text);
		}

		if ($len !== strlen($input)) {
			$unexpected = substr($input, $len, 20);
			throw new InvalidArgumentException("Unexpected '$unexpected' at offset $len in CSS.");
		}

		return $tokens;
	}


	/**
	 * Builds a CSS declarations string from [property, value] pairs.
	 * @param  list<array{string, string}>  $declarations
	 */
	private static function buildDeclarations(array $declarations): string
	{
		$parts = [];
		foreach ($declarations as [$property, $value]) {
			$parts[] = "$property: $value";
		}
		return implode('; ', $parts);
	}
}
