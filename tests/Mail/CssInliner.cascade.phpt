<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\CssInliner cascade -- specificity, importance and source order.
 * @phpVersion 8.4
 */

use Nette\Mail\CssInliner;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Specificity

test('a more specific selector wins over a later, less specific one', function () {
	$result = (new CssInliner)
		->addCss('p.intro { color: red; } p { color: blue; }')
		->inline('<html><body><p class="intro">Hello</p></body></html>');

	// source order would have said blue; specificity says red, as a browser would
	Assert::contains('<p class="intro" style="color: blue; color: red">Hello</p>', $result);
});


test('an id beats any number of classes', function () {
	$result = (new CssInliner)
		->addCss('#main { color: red; } .a.b.c { color: blue; }')
		->inline('<html><body><p id="main" class="a b c">Hello</p></body></html>');

	Assert::contains('style="color: blue; color: red"', $result);
});


test('a class beats a type selector', function () {
	$result = (new CssInliner)
		->addCss('.hi { color: red; } p { color: blue; }')
		->inline('<html><body><p class="hi">Hello</p></body></html>');

	Assert::contains('style="color: blue; color: red"', $result);
});


test('a descendant selector is more specific than a bare type', function () {
	$result = (new CssInliner)
		->addCss('p { color: blue; } div p { color: red; }')
		->inline('<html><body><div><p>Hello</p></div></body></html>');

	Assert::contains('style="color: blue; color: red"', $result);
});


test('equal specificity falls back to source order', function () {
	$result = (new CssInliner)
		->addCss('.a { color: red; } .b { color: blue; }')
		->inline('<html><body><p class="a b">Hello</p></body></html>');

	Assert::contains('style="color: red; color: blue"', $result);
});


test('each selector in a list carries its own specificity', function () {
	$result = (new CssInliner)
		->addCss('p.intro, div { color: red; } p { color: blue; }')
		->inline('<html><body><p class="intro">Hello</p></body></html>');

	// p.intro (0,1,1) beats the later p (0,0,1)
	Assert::contains('style="color: blue; color: red"', $result);
});


test('an attribute selector counts as a class', function () {
	$result = (new CssInliner)
		->addCss('p { color: blue; } [data-x] { color: red; }')
		->inline('<html><body><p data-x="1">Hello</p></body></html>');

	Assert::contains('style="color: blue; color: red"', $result);
});


test('the universal selector adds no specificity', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; } * { color: blue; }')
		->inline('<html><body><p>Hello</p></body></html>');

	Assert::contains('<p style="color: blue; color: red">Hello</p>', $result);
});


// Importance

test('!important beats a more specific normal declaration', function () {
	$result = (new CssInliner)
		->addCss('p { color: red !important; } p#main.intro { color: blue; }')
		->inline('<html><body><p id="main" class="intro">Hello</p></body></html>');

	Assert::contains('style="color: blue; color: red !important"', $result);
});


test('!important beats an existing inline style', function () {
	$result = (new CssInliner)
		->addCss('p { color: red !important; }')
		->inline('<html><body><p style="color: blue">Hello</p></body></html>');

	Assert::contains('style="color: blue; color: red !important"', $result);
});


test('a normal declaration never beats an inline style', function () {
	$result = (new CssInliner)
		->addCss('p#main { color: red; }')
		->inline('<html><body><p id="main" style="color: blue">Hello</p></body></html>');

	Assert::contains('style="color: red; color: blue"', $result);
});


test('an important inline style beats an important rule', function () {
	$result = (new CssInliner)
		->addCss('p { color: red !important; }')
		->inline('<html><body><p style="color: blue !important">Hello</p></body></html>');

	Assert::contains('style="color: red !important; color: blue !important"', $result);
});


test('between two important declarations, specificity still decides', function () {
	$result = (new CssInliner)
		->addCss('p { color: red !important; } p.intro { color: green !important; }')
		->inline('<html><body><p class="intro">Hello</p></body></html>');

	Assert::contains('style="color: red !important; color: green !important"', $result);
});


// Fallbacks

test('every rule setting a property is written out, the winner last', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; } p { color: green; } p { color: blue; }')
		->inline('<html><body><p>Hello</p></body></html>');

	Assert::contains('<p style="color: red; color: green; color: blue">Hello</p>', $result);
});


test('a fallback within one rule survives', function () {
	$result = (new CssInliner)
		->addCss('div { display: -webkit-box; display: flex; }')
		->inline('<html><body><div>Hello</div></body></html>');

	Assert::contains('<div style="display: -webkit-box; display: flex">Hello</div>', $result);
});


test('a fallback across rules is ordered by rank, not by source', function () {
	$result = (new CssInliner)
		->addCss('.box { display: flex; } div { display: -webkit-box; }')
		->inline('<html><body><div class="box">Hello</div></body></html>');

	Assert::contains('<div class="box" style="display: -webkit-box; display: flex">Hello</div>', $result);
});


test('a candidate repeated by a higher-ranked one is dropped', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; } .x { color: blue; } #y { color: red; }')
		->inline('<html><body><p id="y" class="x">Hello</p><p class="x">World</p></body></html>');

	Assert::contains('<p id="y" class="x" style="color: blue; color: red">Hello</p>', $result);
	Assert::contains('<p class="x" style="color: red; color: blue">World</p>', $result);
});


test('identical values collapse into one declaration', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; } .x { color: red; }')
		->inline('<html><body><p class="x" style="color: red">Hello</p></body></html>');

	Assert::contains('<p class="x" style="color: red">Hello</p>', $result);
});


test('an !important rule ends up last even against an inline style', function () {
	$result = (new CssInliner)
		->addCss('p { display: block !important; } .x { display: flex; }')
		->inline('<html><body><p class="x" style="display: -webkit-box; display: grid">Hello</p></body></html>');

	Assert::contains('style="display: flex; display: -webkit-box; display: grid; display: block !important"', $result);
});


test('getRules() returns the last value of each property', function () {
	Assert::same(
		[['div', ['display' => 'flex', 'color' => 'red']]],
		(new CssInliner)->addCss('div { display: -webkit-box; color: red; display: flex; }')->getRules(),
	);
});


// Output

test('non-conflicting properties from every source are kept, in source order', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><head><style>p { font-size: 14px; }</style></head><body><p style="margin: 10px">Hello</p></body></html>');

	Assert::contains('<p style="font-size: 14px; color: red; margin: 10px">Hello</p>', $result);
});


test('an HTML attribute is generated without the importance marker', function () {
	$result = (new CssInliner)
		->addCss('td { background-color: #fff !important; width: 600px !important; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::contains('bgcolor="#fff"', $result);
	Assert::contains('width="600"', $result);
	Assert::notContains('bgcolor="#fff !important"', $result);
});


test('an unparseable inline style is left alone rather than dropped', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><body><p style="color: §§§">Hello</p></body></html>');

	Assert::contains('color: §§§', $result);
});


test('a brace smuggled into an inline style does not become a rule of its own', function () {
	$result = (new CssInliner)
		->addCss('p { color: red; }')
		->inline('<html><body><p style="}x{color: green">Hello</p></body></html>');

	// the attribute is not a plain declaration list, so it is kept verbatim, not reinterpreted
	Assert::contains('<p style="color: red; }x{color: green">Hello</p>', $result);
});


// Property names

test('property names are matched case-insensitively', function () {
	$result = (new CssInliner)
		->addCss('p { COLOR: red; } p { color: blue; }')
		->inline('<html><body><p>Hello</p></body></html>');

	// one property, so the later rule ends up last; two separate names would let the loser win in the client
	Assert::contains('<p style="color: red; color: blue">Hello</p>', $result);
});


test('a custom property keeps its case, since it really is case-sensitive', function () {
	Assert::same(
		[['.a', ['--Gap' => '1px', '--gap' => '2px']]],
		(new CssInliner)->addCss('.a { --Gap: 1px; --gap: 2px; }')->getRules(),
	);
});


// HTML attributes

test('a value an attribute cannot express is not turned into one', function () {
	$result = (new CssInliner)
		->addCss('td { width: auto; height: calc(100% - 2px); }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	// casting these to a number would emit width="0" and collapse the cell in Outlook
	Assert::notContains('width=', $result);
	Assert::notContains('height=', $result);
	Assert::contains('width: auto', $result);
});


test('a percentage keeps its unit in the attribute', function () {
	$result = (new CssInliner)
		->addCss('table { width: 50%; }')
		->inline('<html><body><table><tr><td>X</td></tr></table></body></html>');

	Assert::contains('width="50%"', $result);
});
