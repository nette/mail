<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Dom;
use Nette\InvalidArgumentException;
use function array_keys, array_merge, count, implode, in_array, preg_match_all, spl_object_id, strlen, strtolower, substr, trim;


/**
 * Applies CSS rules as inline styles to HTML elements using DOM CSS selectors.
 * Requires PHP 8.4+ for Dom\HTMLDocument support.
 */
class CssInliner
{
	private const Patterns = [
		self::T_Comment => '/\*[^*]*\*+(?:[^/*][^*]*\*+)*/',
		self::T_Whitespace => '[\s]+',
		self::T_String => '"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'',
		self::T_Url => 'url\(\s*(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'|[^)]*?)\s*\)',
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
		'background-color' => ['bgcolor',     'string', ['table', 'td', 'th', 'body', 'tr']],
		'width'            => ['width',       'int',    ['table', 'td', 'th', 'img']],
		'height'           => ['height',      'int',    ['table', 'td', 'th', 'img']],
		'border-spacing'   => ['cellspacing', 'int',    ['table']],
	];

	/** @var list<array{string, array<string, string>}> */
	private array $rules = [];


	/**
	 * Adds CSS stylesheet rules to be applied during inlining.
	 */
	public function addCss(string $css): static
	{
		$this->rules = array_merge($this->rules, self::parseStylesheet($css));
		return $this;
	}


	/**
	 * Returns the collected rules as [selector, declarations] pairs.
	 * @return list<array{string, array<string, string>}>
	 */
	public function getRules(): array
	{
		return $this->rules;
	}


	/**
	 * Applies all added CSS rules as inline styles to the given HTML.
	 * Also extracts and inlines rules from <style> tags (which are preserved).
	 * Existing inline styles on elements take precedence over all rules.
	 */
	public function inline(string $html): string
	{
		$doc = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

		$styleRules = [];
		foreach ($doc->querySelectorAll('style') as $styleEl) {
			$styleRules = array_merge($styleRules, self::parseStylesheet($styleEl->textContent ?? ''));
		}

		/** @var array<int, array<string, string>> */
		$collectedStyles = [];
		/** @var array<int, Dom\Element> */
		$elements = [];
		$allRules = array_merge($styleRules, $this->rules);

		foreach ($allRules as [$selector, $declarations]) {
			foreach ($doc->querySelectorAll($selector) as $element) {
				$id = spl_object_id($element);
				$elements[$id] = $element;
				$collectedStyles[$id] = array_merge($collectedStyles[$id] ?? [], $declarations);
			}
		}

		// Prepend collected styles before existing inline style (last declaration wins)
		foreach ($collectedStyles as $id => $declarations) {
			$element = $elements[$id];
			$css = self::buildDeclarations($declarations);
			$existing = $element->getAttribute('style');
			$element->setAttribute('style', $css . ($existing ? '; ' . $existing : ''));

			// Generate HTML attributes for email client compatibility (Outlook)
			$tag = strtolower($element->tagName);
			foreach (self::HtmlAttributes as $cssProp => [$attr, $type, $tags]) {
				if (isset($declarations[$cssProp]) && in_array($tag, $tags, true)) {
					$value = $declarations[$cssProp];
					if ($type === 'int' && !str_contains($value, '%')) {
						$value = (string) (int) $value;
					}

					$element->setAttribute($attr, $value);
				}
			}
		}

		return $doc->saveHtml();
	}


	/**
	 * Parses CSS stylesheet text into a list of selector + declarations pairs.
	 * @return list<array{string, array<string, string>}>
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
	 * Parses a CSS block, collecting declarations and recursing into nested rules.
	 * @param  list<array{int|string, string}>  $tokens
	 * @param  list<array{string, array<string, string>}>  &$rules
	 */
	private static function parseBlock(array $tokens, int &$i, string $parentSelector, array &$rules): void
	{
		$count = count($tokens);
		/** @var array<string, string> */
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
					if ($property !== '' && $value !== '') {
						$declarations[$property] = $value;
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
	 * Builds a CSS declarations string from property => value pairs.
	 * @param  array<string, string>  $declarations
	 */
	private static function buildDeclarations(array $declarations): string
	{
		$parts = [];
		foreach ($declarations as $property => $value) {
			$parts[] = "$property: $value";
		}
		return implode('; ', $parts);
	}
}
