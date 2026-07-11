<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer authentication.
 */

use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


test('PLAIN is preferred when the server offers it', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN PLAIN\r\n235 authenticated\r\n",
		username: 'user@example.com',
		password: 'secret',
	);
	$mailer->connect();

	Assert::same(
		['EHLO localhost', 'AUTH PLAIN ' . base64_encode("user@example.com\0user@example.com\0secret")],
		$mailer->getWrittenLines(),
	);
});


test('LOGIN is used when PLAIN is not offered', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN\r\n334 username\r\n334 password\r\n235 authenticated\r\n",
		username: 'user@example.com',
		password: 'secret',
	);
	$mailer->connect();

	Assert::same(
		['EHLO localhost', 'AUTH LOGIN', base64_encode('user@example.com'), base64_encode('secret')],
		$mailer->getWrittenLines(),
	);
});


test('the legacy AUTH=PLAIN advertisement is understood', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH=LOGIN PLAIN\r\n235 authenticated\r\n",
		username: 'user@example.com',
		password: 'secret',
	);
	$mailer->connect();

	Assert::contains('AUTH PLAIN ', $mailer->getWritten());
});


test('an empty password still sends the password line, instead of stalling', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN\r\n334 username\r\n334 password\r\n235 authenticated\r\n",
		username: 'user@example.com',
		password: '',
	);
	$mailer->connect();

	// the trailing empty line is the password: skipping it would leave the server waiting for it
	Assert::same(
		"EHLO localhost\r\nAUTH LOGIN\r\n" . base64_encode('user@example.com') . "\r\n\r\n",
		$mailer->getWritten(),
	);
});


test('a server without AUTH support says so plainly', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250 mail.example.com\r\n",
		username: 'user@example.com',
		password: 'secret',
	);

	Assert::exception(
		fn() => $mailer->connect(),
		SmtpException::class,
		'SMTP server does not support authentication.',
	);
});


test('a server offering only mechanisms we cannot do says which ones', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH CRAM-MD5 DIGEST-MD5\r\n",
		username: 'user@example.com',
		password: 'secret',
	);

	Assert::exception(
		fn() => $mailer->connect(),
		SmtpException::class,
		'SMTP server does not offer a supported authentication mechanism, only: CRAM-MD5, DIGEST-MD5.',
	);
});


test('no credentials means no authentication attempt', function () {
	$mailer = new SmtpMailerMock("220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN PLAIN\r\n");
	$mailer->connect();

	Assert::same(['EHLO localhost'], $mailer->getWrittenLines());
});
