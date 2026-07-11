<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message one-click unsubscribe (RFC 2369, RFC 8058).
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('a URL yields both headers, since one-click needs the Post header too', function () {
	$mail = new Message;
	$mail->setUnsubscribe('https://example.com/unsubscribe?id=abc');

	Assert::same('<https://example.com/unsubscribe?id=abc>', $mail->getHeader('List-Unsubscribe'));
	Assert::same('List-Unsubscribe=One-Click', $mail->getHeader('List-Unsubscribe-Post'));
});


test('an address alone is a mailto fallback and announces no one-click', function () {
	$mail = new Message;
	$mail->setUnsubscribe(email: 'unsub@example.com');

	Assert::same('<mailto:unsub@example.com>', $mail->getHeader('List-Unsubscribe'));
	Assert::null($mail->getHeader('List-Unsubscribe-Post'));
});


test('both targets are listed, URL first', function () {
	$mail = new Message;
	$mail->setUnsubscribe('https://example.com/unsub', 'unsub@example.com');

	Assert::same(
		'<https://example.com/unsub>, <mailto:unsub@example.com>',
		$mail->getHeader('List-Unsubscribe'),
	);
	Assert::same('List-Unsubscribe=One-Click', $mail->getHeader('List-Unsubscribe-Post'));
});


test('an unsubscribe address on an IDN domain is punycoded', function () {
	if (!function_exists('idn_to_ascii')) {
		return;
	}

	$mail = new Message;
	$mail->setUnsubscribe(email: 'unsub@příklad.cz');

	Assert::same('<mailto:unsub@xn--pklad-zsa96e.cz>', $mail->getHeader('List-Unsubscribe'));
});


test('the headers reach the generated message', function () {
	$mail = new Message;
	$mail->setFrom('news@example.com');
	$mail->addTo('jane@example.com');
	$mail->setUnsubscribe('https://example.com/unsub');
	$mail->setBody('News');

	$message = $mail->generateMessage();
	Assert::contains("List-Unsubscribe: <https://example.com/unsub>\r\n", $message);
	Assert::contains("List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n", $message);
});


test('switching to a mailto-only target drops the stale one-click header', function () {
	$mail = new Message;
	$mail->setUnsubscribe('https://example.com/unsub');
	$mail->setUnsubscribe(email: 'unsub@example.com');

	Assert::null($mail->getHeader('List-Unsubscribe-Post'));
});


test('nothing to unsubscribe with is an error', function () {
	$mail = new Message;

	Assert::exception(
		fn() => $mail->setUnsubscribe(),
		Nette\InvalidArgumentException::class,
		'Provide an unsubscribe URL, an email address, or both.',
	);
});


test('a bad URL or address is rejected', function () {
	$mail = new Message;

	Assert::exception(fn() => $mail->setUnsubscribe('not a url'), Nette\Utils\AssertionException::class);
	Assert::exception(fn() => $mail->setUnsubscribe(email: 'not an email'), Nette\Utils\AssertionException::class);
});


test('DKIM covers the unsubscribe headers when they are present', function () {
	if (!extension_loaded('openssl')) {
		return;
	}

	$signer = new Nette\Mail\DkimSigner('nette.org', 'selector', file_get_contents(__DIR__ . '/fixtures/private.key'));

	$mail = new Message;
	$mail->setFrom('news@example.com');
	$mail->addTo('jane@example.com');
	$mail->setUnsubscribe('https://example.com/unsub');
	$mail->setBody('News');

	Assert::match('%A%h=%a%List-Unsubscribe:List-Unsubscribe-Post;%A%', $signer->generateSignedMessage($mail));
});
