<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\HtmlComposer
 */

use Nette\Mail\HtmlComposer;
use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('applyTo sets HTML body, generates text and extracts subject from <title>', function () {
	$mail = new Message;
	(new HtmlComposer('<html><head><title>Hello</title></head><body><p>World</p></body></html>'))
		->applyTo($mail);

	Assert::same('Hello', $mail->getSubject());
	Assert::same('<html><head></head><body><p>World</p></body></html>', $mail->getHtmlBody());
	Assert::same('World', $mail->getBody());
});


test('applyTo respects already-set subject and body', function () {
	$mail = new Message;
	$mail->setSubject('Custom subject');
	$mail->setBody('Custom text');

	(new HtmlComposer('<html><head><title>Ignored</title></head><body><p>Hi</p></body></html>'))
		->applyTo($mail);

	Assert::same('Custom subject', $mail->getSubject());
	Assert::same('Custom text', $mail->getBody());
	// <title> is left untouched in HTML when subject was already set
	Assert::same('<html><head><title>Ignored</title></head><body><p>Hi</p></body></html>', $mail->getHtmlBody());
});


test('applyTo normalizes line endings and strips leading newlines', function () {
	$mail = new Message;
	(new HtmlComposer("\n\r\n<p>Hi</p>\r\nLine 2"))
		->applyTo($mail);

	Assert::same("<p>Hi</p>\nLine 2", $mail->getHtmlBody());
});


test('inlineCss() without argument inlines <style> tags from HTML', function () {
	$mail = new Message;
	(new HtmlComposer('<html><head><style>p { color: red; }</style></head><body><p>Hi</p></body></html>'))
		->inlineCss()
		->applyTo($mail);

	Assert::same(
		'<html><head><style>p { color: red; }</style></head><body><p style="color: red">Hi</p></body></html>',
		$mail->getHtmlBody(),
	);
});


test('inlineCss($css) adds external stylesheet', function () {
	$mail = new Message;
	(new HtmlComposer('<html><body><p>Hi</p></body></html>'))
		->inlineCss('p { color: red; }')
		->applyTo($mail);

	Assert::same(
		'<html><head></head><body><p style="color: red">Hi</p></body></html>',
		$mail->getHtmlBody(),
	);
});


test('inlineCss() accumulates over multiple calls', function () {
	$mail = new Message;
	(new HtmlComposer('<html><body><p>Hi</p></body></html>'))
		->inlineCss('p { color: red; }')
		->inlineCss('p { font-size: 14px; }')
		->applyTo($mail);

	Assert::same(
		'<html><head></head><body><p style="color: red; font-size: 14px">Hi</p></body></html>',
		$mail->getHtmlBody(),
	);
});


test('without inlineCss(), <style> tags are left as-is', function () {
	$mail = new Message;
	(new HtmlComposer('<html><head><style>p { color: red; }</style></head><body><p>Hi</p></body></html>'))
		->applyTo($mail);

	Assert::same(
		'<html><head><style>p { color: red; }</style></head><body><p>Hi</p></body></html>',
		$mail->getHtmlBody(),
	);
});


test('embedImages() embeds local images and rewrites src to cid:', function () {
	$mail = new Message;
	(new HtmlComposer('<html><body><p>Hi</p><img src="background.png"></body></html>'))
		->embedImages(__DIR__ . '/fixtures')
		->applyTo($mail);

	Assert::match(
		'<html><body><p>Hi</p><img src="cid:%S%@%S%"></body></html>',
		$mail->getHtmlBody(),
	);
});


test('inlineCss + embedImages combined', function () {
	$mail = new Message;
	(new HtmlComposer('<html><body><p>Hi</p><img src="background.png"></body></html>'))
		->inlineCss('p { color: red; } img { border: 0; }')
		->embedImages(__DIR__ . '/fixtures')
		->applyTo($mail);

	Assert::match(
		'<html><head></head><body><p style="color: red">Hi</p><img src="cid:%S%@%S%" style="border: 0"></body></html>',
		$mail->getHtmlBody(),
	);
});


test('htmlToText converts HTML to plain text', function () {
	Assert::same(
		"Hello world\n\nLine 2",
		HtmlComposer::htmlToText('<p>Hello <b>world</b></p><p>Line 2</p>'),
	);
});


test('htmlToText strips <style>, <script>, <head> blocks', function () {
	Assert::same(
		'Hi',
		HtmlComposer::htmlToText('<head><title>T</title></head><style>p {}</style><script>x</script><body><p>Hi</p></body>'),
	);
});


test('htmlToText converts links to "text <url>" format', function () {
	Assert::same(
		'click here <https://example.com>',
		HtmlComposer::htmlToText('<a href="https://example.com">click here</a>'),
	);
});


test('applyTo can be called on multiple messages with same composer', function () {
	$composer = (new HtmlComposer('<html><body><p>Shared</p></body></html>'))
		->inlineCss('p { color: blue; }');

	$mail1 = new Message;
	$mail2 = new Message;
	$composer->applyTo($mail1);
	$composer->applyTo($mail2);

	$expected = '<html><head></head><body><p style="color: blue">Shared</p></body></html>';
	Assert::same($expected, $mail1->getHtmlBody());
	Assert::same($expected, $mail2->getHtmlBody());
});
