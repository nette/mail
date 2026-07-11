<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer STARTTLS negotiation.
 */

use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


test('STARTTLS is negotiated before the second EHLO', function () {
	$mailer = new SmtpMailerMock("220 ready\r\n250 OK\r\n220 go ahead\r\n", encryption: 'tls');

	// the socket pair cannot do crypto, so the handshake fails -- but only after STARTTLS was sent
	Assert::exception(fn() => $mailer->connect(), SmtpException::class, 'Unable to connect via TLS%a%');

	Assert::same(
		['EHLO localhost', 'STARTTLS'],
		$mailer->getWrittenLines(),
	);
});


test('a failed handshake surfaces as SmtpException, not a PHP warning', function () {
	$mailer = new SmtpMailerMock("220 ready\r\n250 OK\r\n220 go ahead\r\n", encryption: 'tls');

	$e = Assert::exception(fn() => $mailer->connect(), SmtpException::class);

	// the underlying reason is carried over instead of being swallowed
	Assert::contains('does not support SSL/crypto', $e->getMessage());
});
