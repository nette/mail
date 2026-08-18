<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message quotes an encoded display name only when it could be re-parsed.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('ASCII name outside atext is a real quoted-string', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'John.Doe');

	Assert::same('"John.Doe" <doe@example.com>', $mail->getEncodedHeader('From'));
});


test('ASCII name within atext is left alone', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'John Doe');

	Assert::same('John Doe <doe@example.com>', $mail->getEncodedHeader('From'));
});


test('encoded name carries no quotes', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'Jan Nováček');

	$header = $mail->getEncodedHeader('From');
	Assert::same('=?UTF-8?B?SmFuIE5vdsOhxI1law==?= <doe@example.com>', $header);
	Assert::same('Jan Nováček <doe@example.com>', iconv_mime_decode($header, 0, 'UTF-8'));
});


test('a dot does not make an encoded name quoted', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'Objednávky domena.cz');

	Assert::same(
		'Objednávky domena.cz <doe@example.com>',
		iconv_mime_decode($mail->getEncodedHeader('From'), 0, 'UTF-8'),
	);
});


test('a comma keeps the encoded name quoted', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'Nováček, Jan');

	Assert::same(
		'"Nováček, Jan" <doe@example.com>',
		iconv_mime_decode($mail->getEncodedHeader('From'), 0, 'UTF-8'),
	);
});


test('a quote in an encoded name is escaped', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'Jan "Honza" Nováček');

	Assert::same(
		'"Jan \"Honza\" Nováček" <doe@example.com>',
		iconv_mime_decode($mail->getEncodedHeader('From'), 0, 'UTF-8'),
	);
});


test('an address-like name stays quoted', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com', 'Nováček <spoof@example.com>');

	Assert::contains(
		'"Nováček <spoof@example.com>"',
		iconv_mime_decode($mail->getEncodedHeader('From'), 0, 'UTF-8'),
	);
});
