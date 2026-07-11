<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\MimePart header names are case-insensitive (RFC 5322).
 */

use Nette\Mail\Message;
use Nette\Mail\MimePart;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('a header is found regardless of the case used to look it up', function () {
	$mail = new Message;
	$mail->setSubject('Hello');

	Assert::same('Hello', $mail->getHeader('Subject'));
	Assert::same('Hello', $mail->getHeader('subject'));
	Assert::same('Hello', $mail->getHeader('SUBJECT'));
	Assert::same('Hello', $mail->getHeader('sUbJeCt'));
});


test('setting a header under a different case overwrites it instead of duplicating it', function () {
	$part = new MimePart;
	$part->setHeader('X-Custom', 'first');
	$part->setHeader('x-custom', 'second');

	Assert::same(['X-Custom' => 'second'], $part->getHeaders()); // the spelling first used is kept
	Assert::same('second', $part->getHeader('X-CUSTOM'));
});


test('clearing a header is case-insensitive too', function () {
	$mail = new Message;
	$mail->setSubject('Hello');
	$mail->clearHeader('SUBJECT');

	Assert::null($mail->getHeader('Subject'));
});


test('appending recipients is case-insensitive', function () {
	$mail = new Message;
	$mail->addTo('jane@example.com');
	$mail->setHeader('TO', ['john@example.com' => null], append: true);

	Assert::same(
		['jane@example.com' => null, 'john@example.com' => null],
		$mail->getHeader('to'),
	);
});


test('the encoded header is reachable under any case', function () {
	$mail = new Message;
	$mail->setSubject('Hello');

	Assert::same('Hello', $mail->getEncodedHeader('subject'));
	Assert::null($mail->getEncodedHeader('x-missing'));
});


test('a multipart message keeps its boundary whatever the spelling of Content-Type', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com');
	$mail->setHeader('content-type', 'text/plain; charset=UTF-8'); // a non-canonical spelling, set up front
	$mail->setHtmlBody('<b>Hi</b>');

	$message = $mail->generateMessage();

	// without the boundary the multipart body is unparseable by every mail client
	Assert::match('%A%multipart/alternative;%A%boundary="%a%"%A%', $message);
});


test('the generated message uses the spelling the header was first set with', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com');
	$mail->setSubject('Hello'); // the API spells it canonically
	$mail->setHeader('SUBJECT', 'Goodbye'); // a later write under another case updates the same header
	$mail->setBody('Hi');

	$message = $mail->generateMessage();
	Assert::contains("Subject: Goodbye\r\n", $message);
	Assert::notContains('SUBJECT:', $message);
});
