<?php declare(strict_types=1);

/**
 * Test: CssInliner tokenizer
 * @phpVersion 8.4
 */

use Nette\Mail\CssInliner;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function tokenize(string $input): array
{
	$method = new ReflectionMethod(CssInliner::class, 'tokenize');
	return $method->invoke(null, $input);
}


function types(string $input): array
{
	return array_column(tokenize($input), 0);
}


function texts(string $input): array
{
	return array_column(tokenize($input), 1);
}


$T_Comment = 1;
$T_Whitespace = 2;
$T_String = 3;
$T_Url = 4;
$T_Escape = 5;
$T_AtIdent = 6;
$T_Hash = 7;
$T_Number = 8;
$T_Ident = 9;
$T_Char = 10;


test('empty input', function () {
	Assert::same([], tokenize(''));
});


test('identifiers', function () use ($T_Ident) {
	Assert::same([[$T_Ident, 'color']], tokenize('color'));
	Assert::same([[$T_Ident, 'font-size']], tokenize('font-size'));
	Assert::same([[$T_Ident, '-webkit-transform']], tokenize('-webkit-transform'));
	Assert::same([[$T_Ident, '_private']], tokenize('_private'));
});


test('CSS custom properties', function () use ($T_Ident) {
	Assert::same([[$T_Ident, '--my-var']], tokenize('--my-var'));
	Assert::same([[$T_Ident, '--grid-width']], tokenize('--grid-width'));
});


test('numbers', function () use ($T_Number) {
	Assert::same([[$T_Number, '42']], tokenize('42'));
	Assert::same([[$T_Number, '3.14']], tokenize('3.14'));
	Assert::same([[$T_Number, '.5']], tokenize('.5'));
	Assert::same([[$T_Number, '-10']], tokenize('-10'));
	Assert::same([[$T_Number, '+3']], tokenize('+3'));
	Assert::same([[$T_Number, '100%']], tokenize('100%'));
	Assert::same([[$T_Number, '16px']], tokenize('16px'));
	Assert::same([[$T_Number, '1.5em']], tokenize('1.5em'));
	Assert::same([[$T_Number, '2rem']], tokenize('2rem'));
});


test('hash tokens', function () use ($T_Hash) {
	Assert::same([[$T_Hash, '#fff']], tokenize('#fff'));
	Assert::same([[$T_Hash, '#a0704e']], tokenize('#a0704e'));
	Assert::same([[$T_Hash, '#0000001c']], tokenize('#0000001c'));
});


test('double-quoted strings', function () use ($T_String) {
	Assert::same([[$T_String, '"hello"']], tokenize('"hello"'));
	Assert::same([[$T_String, '"with space"']], tokenize('"with space"'));
	Assert::same([[$T_String, '"esc\"aped"']], tokenize('"esc\"aped"'));
	Assert::same([[$T_String, '"{braces}"']], tokenize('"{braces}"'));
	Assert::same([[$T_String, '"semi;colon"']], tokenize('"semi;colon"'));
});


test('single-quoted strings', function () use ($T_String) {
	Assert::same([[$T_String, "'hello'"]], tokenize("'hello'"));
	Assert::same([[$T_String, "'esc\\'aped'"]], tokenize("'esc\\'aped'"));
});


test('url with unquoted content', function () use ($T_Url) {
	Assert::same([[$T_Url, 'url(image.png)']], tokenize('url(image.png)'));
	Assert::same([[$T_Url, 'url(path/to/file.css)']], tokenize('url(path/to/file.css)'));
});


test('url with double-quoted content', function () use ($T_Url) {
	Assert::same([[$T_Url, 'url("image.png")']], tokenize('url("image.png")'));
	Assert::same([[$T_Url, 'url( "image.png" )']], tokenize('url( "image.png" )'));
});


test('url with single-quoted content', function () use ($T_Url) {
	Assert::same([[$T_Url, "url('image.png')"]], tokenize("url('image.png')"));
	Assert::same([[$T_Url, "url( 'image.png' )"]], tokenize("url( 'image.png' )"));
});


test('url with data URI containing semicolons', function () use ($T_Url) {
	Assert::same(
		[[$T_Url, 'url(data:image/png;base64,abc123)']],
		tokenize('url(data:image/png;base64,abc123)'),
	);
});


test('url with quoted data URI', function () use ($T_Url) {
	Assert::same(
		[[$T_Url, 'url("data:image/png;base64,abc")']],
		tokenize('url("data:image/png;base64,abc")'),
	);
});


test('url with empty content', function () use ($T_Url) {
	Assert::same([[$T_Url, 'url()']], tokenize('url()'));
	Assert::same([[$T_Url, 'url(  )']], tokenize('url(  )'));
});


test('url with escaped characters in quoted string', function () use ($T_Url) {
	Assert::same(
		[[$T_Url, 'url("path/to/file\"name.png")']],
		tokenize('url("path/to/file\"name.png")'),
	);
});


test('url with unterminated double quote does not match as url', function () use ($T_Ident) {
	Assert::exception(
		fn() => tokenize('url("unclosed)'),
		Nette\InvalidArgumentException::class,
	);
});


test('url with unterminated single quote does not match as url', function () use ($T_Ident) {
	Assert::exception(
		fn() => tokenize("url('unclosed)"),
		Nette\InvalidArgumentException::class,
	);
});


test('url unquoted must not contain quotes', function () {
	Assert::exception(
		fn() => tokenize("url(it's)"),
		Nette\InvalidArgumentException::class,
	);
	Assert::exception(
		fn() => tokenize('url(a"b)'),
		Nette\InvalidArgumentException::class,
	);
});


test('comments', function () use ($T_Comment) {
	Assert::same([[$T_Comment, '/* comment */']], tokenize('/* comment */'));
	Assert::same([[$T_Comment, "/* multi\nline */"]], tokenize("/* multi\nline */"));
	Assert::same([[$T_Comment, '/* with * stars **/']], tokenize('/* with * stars **/'));
});


test('whitespace', function () use ($T_Whitespace) {
	Assert::same([[$T_Whitespace, ' ']], tokenize(' '));
	Assert::same([[$T_Whitespace, "  \t\n  "]], tokenize("  \t\n  "));
});


test('escape sequences', function () use ($T_Escape) {
	Assert::same([[$T_Escape, '\#']], tokenize('\#'));
	Assert::same([[$T_Escape, '\.']], tokenize('\.'));
});


test('at-identifiers', function () use ($T_AtIdent) {
	Assert::same([[$T_AtIdent, '@media']], tokenize('@media'));
	Assert::same([[$T_AtIdent, '@keyframes']], tokenize('@keyframes'));
	Assert::same([[$T_AtIdent, '@-webkit-keyframes']], tokenize('@-webkit-keyframes'));
	Assert::same([[$T_AtIdent, '@container']], tokenize('@container'));
});


test('char tokens are returned as their character', function () {
	Assert::same([['{', '{']], tokenize('{'));
	Assert::same([[')', ')']], tokenize(')'));
	Assert::same([[';', ';']], tokenize(';'));
	Assert::same([[':', ':']], tokenize(':'));
	Assert::same([[',', ',']], tokenize(','));
	Assert::same([['>', '>']], tokenize('>'));
	Assert::same([['~', '~']], tokenize('~'));
	Assert::same([['[', '[']], tokenize('['));
	Assert::same([[']', ']']], tokenize(']'));
	Assert::same([['*', '*']], tokenize('*'));
	Assert::same([['!', '!']], tokenize('!'));
});


test('full declaration tokenization', function () use ($T_Ident, $T_Whitespace, $T_Number) {
	Assert::same(
		[[$T_Ident, 'margin'], [':', ':'], [$T_Whitespace, ' '], [$T_Number, '10px']],
		tokenize('margin: 10px'),
	);
});


test('selector with pseudo-class and attribute', function () use ($T_Ident, $T_Escape, $T_Hash) {
	$tokens = tokenize('a:not([href^=\#])');
	Assert::same('a', $tokens[0][1]);
	Assert::same(':', $tokens[1][1]);
	Assert::same('not', $tokens[2][1]);
	Assert::same('(', $tokens[3][1]);
	Assert::same('[', $tokens[4][1]);
	Assert::same('href', $tokens[5][1]);
	Assert::same('^', $tokens[6][1]);
	Assert::same('=', $tokens[7][1]);
	Assert::same('\#', $tokens[8][1]);
	Assert::same(']', $tokens[9][1]);
	Assert::same(')', $tokens[10][1]);
});


test('complex value with url and keywords', function () use ($T_Ident, $T_Whitespace, $T_Url, $T_String) {
	$tokens = tokenize("white url('images/bg.png') no-repeat center");
	$texts = array_column($tokens, 1);
	Assert::same(['white', ' ', "url('images/bg.png')", ' ', 'no-repeat', ' ', 'center'], $texts);
});


test('url with very long quoted content (JIT stack limit)', function () use ($T_Url) {
	$svg = str_repeat('x', 100_000);
	$input = 'url("data:image/svg+xml,' . $svg . '")';
	Assert::same([[$T_Url, $input]], tokenize($input));
});


test('string with very long content (JIT stack limit)', function () use ($T_String) {
	$content = str_repeat('x', 100_000);
	$input = '"' . $content . '"';
	Assert::same([[$T_String, $input]], tokenize($input));
});


test('unexpected character throws exception', function () {
	Assert::exception(
		fn() => tokenize('§'),
		Nette\InvalidArgumentException::class,
		"Unexpected '§' at offset 0 in CSS.",
	);
});


test('unexpected character in middle throws exception', function () {
	Assert::exception(
		fn() => tokenize('color§red'),
		Nette\InvalidArgumentException::class,
		"Unexpected '§red' at offset 5 in CSS.",
	);
});
