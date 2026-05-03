<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use Nette\Utils\Strings;
use function array_map, array_reverse, explode, implode, is_string, rtrim, strlen, substr, substr_replace, trim, urldecode;


/**
 * Composes HTML email body for a Message: optionally inlines CSS, embeds local images,
 * extracts subject from <title>, sets the HTML body, and generates a plain-text alternative.
 */
class HtmlComposer
{
	private ?string $basePath = null;
	private ?CssInliner $cssInliner = null;


	public function __construct(
		private string $html,
	) {
	}


	/**
	 * Enables embedding of local images referenced in HTML. The path is the base directory
	 * for resolving relative image references in <img src>, <body background>, url(...) in
	 * style attributes/<style> tags, and the [[file]] placeholder.
	 */
	public function embedImages(string $basePath): static
	{
		$this->basePath = $basePath;
		return $this;
	}


	/**
	 * Enables CSS inlining. Without an argument only inlines <style> tags from HTML.
	 * Can be called multiple times to append additional stylesheets.
	 */
	public function inlineCss(?string $css = null): static
	{
		$this->cssInliner ??= new CssInliner;
		if ($css !== null) {
			$this->cssInliner->addCss($css);
		}
		return $this;
	}


	/**
	 * Applies the configured pipeline to the message: inlines CSS, embeds images,
	 * extracts subject from <title> (if not already set), sets the HTML body, and
	 * generates a plain-text alternative (if body is not already set).
	 */
	public function applyTo(Message $mail): void
	{
		$html = $this->html;

		if ($this->cssInliner) {
			$html = $this->cssInliner->inline($html);
		}

		if ($this->basePath !== null) {
			$html = self::embedImagesInHtml($html, $this->basePath, $mail);
		}

		if ($mail->getSubject() == null) { // intentionally ==
			$html = Strings::replace($html, '#<title>(.+?)</title>#is', function (array $m) use ($mail): void {
				$mail->setSubject(Nette\Utils\Html::htmlToText($m[1]));
			});
		}

		$mail->setRawHtmlBody($html);

		if ($mail->getBody() === '' && $html !== '') {
			$mail->setBody(self::htmlToText($html));
		}
	}


	/**
	 * Converts HTML to a plain-text alternative suitable for an email body.
	 */
	public static function htmlToText(string $html): string
	{
		$html = Strings::replace($html, [
			'#<(style|script|head).*</\1>#Uis' => '',
			'#<t[dh][ >]#i' => ' $0',
			'#<a\s[^>]*href=(?|"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)</a>#is' => '$2 &lt;$1&gt;',
			'#[\r\n]+#' => ' ',
			'#<(/?p|/?h\d|li|br|/tr)[ >/]#i' => "\n$0",
		]);
		$text = Nette\Utils\Html::htmlToText($html);
		$text = Strings::replace($text, '#[ \t]+#', ' ');
		$text = implode("\n", array_map('trim', explode("\n", $text)));
		return trim($text);
	}


	private static function embedImagesInHtml(string $html, string $basePath, Message $mail): string
	{
		$cids = [];
		$matches = Strings::matchAll(
			$html,
			'#
				(<img(?:(?!\s src\s*=)[^<>])*+\s src\s*=\s*
				|<body(?:(?!\s background\s*=)[^<>])*+\s background\s*=\s*
				|<(?:(?!\s style\s*=)[^<>])++\s style\s*=\s* ["\'][^"\'>]+[:\s] url\(
				|<style[^>]*>[^<]+ [:\s] url\()
				(?|
					(["\'])(?![a-z]+:|[/\#])([^"\'>]+)
					|()(?![a-z]+:|[/\#])([^"\'>)\s]+)
				)
				|\[\[ ([\w()+./@~-]+) \]\]
			#ix',
			captureOffset: true,
		);
		foreach (array_reverse($matches) as $m) {
			$file = rtrim($basePath, '/\\') . '/' . (isset($m[4]) ? $m[4][0] : urldecode($m[3][0]));
			if (!isset($cids[$file])) {
				$contentId = $mail->addEmbeddedFile($file)->getHeader('Content-ID');
				$cids[$file] = is_string($contentId) ? substr($contentId, 1, -1) : '';
			}

			$html = substr_replace(
				$html,
				"{$m[1][0]}{$m[2][0]}cid:{$cids[$file]}",
				$m[0][1],
				strlen($m[0][0]),
			);
		}
		return $html;
	}
}
