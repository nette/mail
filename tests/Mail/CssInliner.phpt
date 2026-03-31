<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\CssInliner
 * @phpVersion 8.4
 */

use Nette\Mail\CssInliner;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Use case 1: HTML with <style> tag

test('extracts and inlines <style> tag', function () {
	$html = '<html><head><style>p { margin: 0; color: red; }</style></head><body><p>Hello</p></body></html>';
	$result = (new CssInliner)->inline($html);

	Assert::contains('<p style="margin: 0; color: red">Hello</p>', $result);
	Assert::contains('<style>', $result);
});


test('multiple <style> tags', function () {
	$html = '<html><head><style>p { margin: 0; }</style><style>a { color: red; }</style></head><body><p><a href="#">link</a></p></body></html>';
	$result = (new CssInliner)->inline($html);

	Assert::contains('<p style="margin: 0">', $result);
	Assert::contains('<a href="#" style="color: red">', $result);
});


test('<style> with comments', function () {
	$html = '<html><head><style>/* reset */ p { margin: 0; } /* links */ a { color: red; }</style></head><body><p><a href="#">x</a></p></body></html>';
	$result = (new CssInliner)->inline($html);

	Assert::contains('<p style="margin: 0">', $result);
	Assert::contains('<a href="#" style="color: red">', $result);
});


test('<style> skips @-rules', function () {
	$html = '<html><head><style>@media (max-width: 600px) { p { font-size: 14px; } } p { margin: 0; }</style></head><body><p>Hello</p></body></html>';
	$result = (new CssInliner)->inline($html);

	Assert::contains('<p style="margin: 0">Hello</p>', $result);
});


// Use case 2: External CSS via addCss()

test('addCss() with stylesheet string', function () {
	$result = (new CssInliner)
		->addCss('p { margin: 0 0 1.2em; } a { color: #a0704e; }')
		->inline('<html><body><p>Text <a href="#">link</a></p></body></html>');

	Assert::contains('<p style="margin: 0 0 1.2em">', $result);
	Assert::contains('<a href="#" style="color: #a0704e">', $result);
});


test('addCss() with comma-separated selectors', function () {
	$result = (new CssInliner)
		->addCss('ul, ol { padding-left: 1.5em; }')
		->inline('<html><body><ul><li>A</li></ul><ol><li>B</li></ol></body></html>');

	Assert::match('%A%<ul style="padding-left: 1.5em">%A%', $result);
	Assert::match('%A%<ol style="padding-left: 1.5em">%A%', $result);
});


test('addCss() reusable for multiple documents', function () {
	$inliner = (new CssInliner)->addCss('p { margin: 0; }');

	$result1 = $inliner->inline('<html><body><p>One</p></body></html>');
	$result2 = $inliner->inline('<html><body><p>Two</p></body></html>');

	Assert::contains('<p style="margin: 0">One</p>', $result1);
	Assert::contains('<p style="margin: 0">Two</p>', $result2);
});


// Use case 3: Manual rules via addCss()

test('addCss() with single rule', function () {
	$result = (new CssInliner)
		->addCss('p { margin: 0 0 1.2em; }')
		->inline('<html><body><p>Hello</p></body></html>');

	Assert::contains('<p style="margin: 0 0 1.2em">', $result);
});


test('addCss() with class selector', function () {
	$result = (new CssInliner)
		->addCss('.highlight { background: yellow; }')
		->inline('<html><body><p class="highlight">A</p><p>B</p></body></html>');

	Assert::contains('<p class="highlight" style="background: yellow">', $result);
	Assert::contains('<p>B</p>', $result);
});


test('addCss() with complex selector', function () {
	$result = (new CssInliner)
		->addCss('td > p { font-size: 14px; }')
		->inline('<html><body><table><tr><td><p>In table</p></td></tr></table><p>Outside</p></body></html>');

	Assert::contains('<p style="font-size: 14px">In table</p>', $result);
	Assert::contains('<p>Outside</p>', $result);
});


// Cascade order

test('existing inline styles take precedence over everything', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><head><style>p { color: green; }</style></head><body><p style="color: blue">Hello</p></body></html>');

	// addCss overrides <style> via array_merge, then prepended before inline style
	Assert::contains('<p style="color: red; color: blue">Hello</p>', $result);
});


test('addCss() overrides <style> tag for same property', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><head><style>p { color: green; }</style></head><body><p>Hello</p></body></html>');

	Assert::contains('<p style="color: red">Hello</p>', $result);
});


test('addCss() overrides <style> tag for same property', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><head><style>p { color: green; }</style></head><body><p>Hello</p></body></html>');

	Assert::contains('<p style="color: red">Hello</p>', $result);
});


test('later rules override earlier rules', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->addCss('p { color: blue; }')
		->inline('<html><body><p>Hello</p></body></html>');

	Assert::contains('<p style="color: blue">Hello</p>', $result);
});


test('merging non-conflicting properties from multiple sources', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><head><style>p { font-size: 14px; }</style></head><body><p style="margin: 10px">Hello</p></body></html>');

	Assert::contains('<p style="font-size: 14px; color: red; margin: 10px">Hello</p>', $result);
});


test('no matching elements does nothing', function () {
	$result = (new CssInliner)
		->addCss('.nonexistent { color: red; }')
		->inline('<html><body><p>Hello</p></body></html>');

	Assert::notContains('style=', $result);
});


test('preserves HTML structure', function () {
	$result = (new CssInliner)
		->addCss('p { margin: 0; }')
		->inline('<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello</p></body></html>');

	Assert::contains('<title>Test</title>', $result);
	Assert::contains('<p style="margin: 0">Hello</p>', $result);
});


// Tokenizer robustness

test('data: URI with semicolons in value', function () {
	$result = (new CssInliner)
		->addCss('div { background: url(data:image/png;base64,abc123); }')
		->inline('<html><body><div>X</div></body></html>');

	Assert::contains('<div style="background: url(data:image/png;base64,abc123)">X</div>', $result);
});


test('data: URI in <style> tag', function () {
	$result = (new CssInliner)->inline(
		'<html><head><style>div { background: url("data:image/png;base64,abc"); color: red; }</style></head><body><div>X</div></body></html>',
	);

	Assert::contains('background: url(&quot;data:image/png;base64,abc&quot;); color: red', $result);
});


test('unquoted SVG data: URI with parentheses in content', function () {
	$result = (new CssInliner)
		->addCss("div { background-image: url(data:image/svg+xml,<svg><g transform='translate(50,50)'></g></svg>); }")
		->inline('<html><body><div>X</div></body></html>');

	Assert::contains("background-image: url(data:image/svg+xml,<svg><g transform='translate(50,50)'></g></svg>)", $result);
});


test('unquoted SVG data: URI with rgba() in content', function () {
	$result = (new CssInliner)
		->addCss("div { background-image: url(data:image/svg+xml,<svg><rect fill='rgba(255,0,0,0.5)'/></svg>); }")
		->inline('<html><body><div>X</div></body></html>');

	Assert::contains("background-image: url(data:image/svg+xml,<svg><rect fill='rgba(255,0,0,0.5)'/></svg>)", $result);
});


test('unquoted SVG data: URI with url() reference in content', function () {
	$result = (new CssInliner)
		->addCss("div { background-image: url(data:image/svg+xml,<svg><rect fill='url(#grad)'/></svg>); }")
		->inline('<html><body><div>X</div></body></html>');

	Assert::contains("background-image: url(data:image/svg+xml,<svg><rect fill='url(#grad)'/></svg>)", $result);
});


test('braces inside string values in stylesheet', function () {
	$result = (new CssInliner)->inline(
		'<html><head><style>.a { content: "{hello}"; color: red; } .b { margin: 0; }</style></head><body><p class="b">X</p></body></html>',
	);

	Assert::contains('<p class="b" style="margin: 0">X</p>', $result);
});


test('attribute selector with comma', function () {
	$result = (new CssInliner)
		->addCss('[data-value="a,b"] { color: red; }')
		->inline('<html><body><p data-value="a,b">X</p><p>Y</p></body></html>');

	Assert::contains('<p data-value="a,b" style="color: red">X</p>', $result);
	Assert::contains('<p>Y</p>', $result);
});


test('@media rules preserved in <style> element', function () {
	$result = (new CssInliner)->inline(
		'<html><head><style>@media (max-width: 600px) { p { font-size: 14px; } } p { margin: 0; }</style></head><body><p>X</p></body></html>',
	);

	Assert::contains('<style>@media', $result);
	Assert::contains('<p style="margin: 0">X</p>', $result);
});


test('attribute selector with ^=', function () {
	$result = (new CssInliner)
		->addCss('[class^="high"] { color: red; }')
		->inline('<html><body><p class="highlight">X</p><p>Y</p></body></html>');

	Assert::contains('<p class="highlight" style="color: red">X</p>', $result);
	Assert::contains('<p>Y</p>', $result);
});


test('unparseable CSS throws exception', function () {
	Assert::exception(
		fn() => (new CssInliner)->addCss('§ { color: red; }'),
		Nette\InvalidArgumentException::class,
	);
});


// CSS custom properties

test('CSS custom properties and var()', function () {
	Assert::same([
		['.grid', ['--grid-width' => '300px', 'column-gap' => 'var(--gap)', 'margin' => '0']],
	], (new CssInliner)->addCss('.grid { --grid-width: 300px; column-gap: var(--gap); margin: 0; }')->getRules());
});


// CSS escape sequences

test('escaped hash in attribute selector', function () {
	Assert::same([
		['a:not([href^=\#])', ['color' => 'red']],
	], (new CssInliner)->addCss('a:not([href^=\#]) { color: red; }')->getRules());
});


// Nested rules (CSS nesting / SCSS-style)

test('basic nesting', function () {
	Assert::same([
		['.parent', ['color' => 'red']],
		['.parent .child', ['color' => 'blue']],
	], (new CssInliner)->addCss('.parent { color: red; .child { color: blue; } }')->getRules());
});


test('nesting with & (parent reference)', function () {
	Assert::same([
		['a', ['color' => 'red']],
		['a:hover', ['color' => 'blue']],
	], (new CssInliner)->addCss('a { color: red; &:hover { color: blue; } }')->getRules());
});


test('nesting with & in comma-separated selectors', function () {
	Assert::same([
		['.btn', ['color' => 'red']],
		['.btn:hover, .btn:focus', ['color' => 'blue']],
	], (new CssInliner)->addCss('.btn { color: red; &:hover, &:focus { color: blue; } }')->getRules());
});


test('parent declarations emitted before nested rules', function () {
	Assert::same([
		['.topbar', ['position' => 'absolute']],
		['.topbar a', ['color' => 'red']],
	], (new CssInliner)->addCss('.topbar { position: absolute; a { color: red; } }')->getRules());
});


test('deeply nested rules', function () {
	Assert::same([
		['.a .b .c', ['color' => 'red']],
	], (new CssInliner)->addCss('.a { .b { .c { color: red; } } }')->getRules());
});


test('@media inside nested block is skipped', function () {
	Assert::same([
		['.box', ['color' => 'red']],
	], (new CssInliner)->addCss('.box { color: red; @media (max-width: 600px) { font-size: 14px; } }')->getRules());
});


// @-rules

test('container query with < operator is skipped', function () {
	Assert::same([
		['p', ['margin' => '0']],
	], (new CssInliner)->addCss('@container (width < 68ch) { .aside { display: none; } } p { margin: 0; }')->getRules());
});


// Complex real-world values

test('complex values preserved correctly', function () {
	Assert::same([
		['.a', ['box-shadow' => 'inset 0 7px 50px 0 #0000001c']],
		['.b', ['color' => '#808080 !important']],
		['.c', ['font-family' => 'Georgia, "Times New Roman", serif']],
		['.d', ['background' => "white url('images/bg.png') no-repeat center"]],
	], (new CssInliner)->addCss('
		.a { box-shadow: inset 0 7px 50px 0 #0000001c; }
		.b { color: #808080 !important; }
		.c { font-family: Georgia, "Times New Roman", serif; }
		.d { background: white url(\'images/bg.png\') no-repeat center; }
	')->getRules());
});


// HTML attribute generation

test('background-color generates bgcolor on td', function () {
	$result = (new CssInliner)
		->addCss('td { background-color: #fdfbf8; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::contains('<td style="background-color: #fdfbf8" bgcolor="#fdfbf8">X</td>', $result);
});


test('width generates width attribute with px stripped', function () {
	$result = (new CssInliner)
		->addCss('table { width: 600px; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::match('%A%<table style="width: 600px" width="600">%A%', $result);
});


test('text-align does not generate align attribute', function () {
	$result = (new CssInliner)
		->addCss('td { text-align: center; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::contains('<td style="text-align: center">X</td>', $result);
	Assert::notContains('align=', $result);
});


test('border-spacing generates cellspacing on table', function () {
	$result = (new CssInliner)
		->addCss('table { border-spacing: 0; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::match('%A%<table style="border-spacing: 0" cellspacing="0">%A%', $result);
});


test('HTML attributes not generated for unsupported elements', function () {
	$result = (new CssInliner)
		->addCss('span { background-color: red; }')
		->inline('<html><body><span>X</span></body></html>');

	Assert::contains('<span style="background-color: red">X</span>', $result);
});


test('void elements serialized without closing tag', function () {
	$result = (new CssInliner)->inline('<html><body><p>Hello<br>world</p><hr><img src="x.png"></body></html>');

	Assert::contains('<br>', $result);
	Assert::notContains('</br>', $result);
	Assert::notContains('</hr>', $result);
	Assert::notContains('</img>', $result);
});
