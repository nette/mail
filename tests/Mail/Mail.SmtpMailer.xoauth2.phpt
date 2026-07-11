<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer XOAUTH2 authentication.
 */

use Nette\Mail\Message;
use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


$greeting = "220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN PLAIN XOAUTH2\r\n";


test('the token is sent as an XOAUTH2 bearer credential', function () use ($greeting) {
	$mailer = new SmtpMailerMock(
		$greeting . "235 authenticated\r\n",
		username: 'user@example.com',
		password: '',
	);
	$mailer->setAccessToken('ya29.token');
	$mailer->connect();

	Assert::same(
		[
			'EHLO localhost',
			'AUTH XOAUTH2 ' . base64_encode("user=user@example.com\1auth=Bearer ya29.token\1\1"),
		],
		$mailer->getWrittenLines(),
	);
});


test('a callback token is resolved per connection, so it can be refreshed', function () use ($greeting) {
	$calls = 0;
	$mailer = new SmtpMailerMock(
		$greeting . "235 authenticated\r\n",
		username: 'user@example.com',
		password: '',
	);
	$mailer->setAccessToken(function () use (&$calls) {
		$calls++;
		return "token-$calls";
	});

	$mailer->connect();
	Assert::same(1, $calls);
	Assert::contains(base64_encode("user=user@example.com\1auth=Bearer token-1\1\1"), $mailer->getWritten());

	$mailer->disconnect();
	$mailer->connect();
	Assert::same(2, $calls);
	Assert::contains(base64_encode("user=user@example.com\1auth=Bearer token-2\1\1"), $mailer->getWritten());
});


test('XOAUTH2 takes precedence over PLAIN when a token is set', function () use ($greeting) {
	$mailer = new SmtpMailerMock(
		$greeting . "235 authenticated\r\n",
		username: 'user@example.com',
		password: 'secret',
	);
	$mailer->setAccessToken('ya29.token');
	$mailer->connect();

	Assert::notContains('AUTH PLAIN', $mailer->getWritten());
});


test('a rejected token is answered with an empty line and reported', function () use ($greeting) {
	// the server challenges with 334 + base64 error, waits for an empty line, then fails the exchange
	$mailer = new SmtpMailerMock(
		$greeting . "334 eyJzdGF0dXMiOiI0MDEifQ==\r\n535 invalid credentials\r\n",
		username: 'user@example.com',
		password: '',
	);
	$mailer->setAccessToken('expired');

	Assert::exception(
		fn() => $mailer->connect(),
		SmtpException::class,
		'SMTP server did not accept XOAUTH2 credentials with error: 535 invalid credentials',
	);

	// the exchange ends with the empty line the server is waiting for
	Assert::true(str_ends_with($mailer->getWritten(), "\r\n\r\n"));
});


test('a throwing token callback does not leave an unauthenticated connection behind', function () use ($greeting) {
	$transaction = "235 authenticated\r\n250 sender\r\n250 recipient\r\n354 go ahead\r\n250 queued\r\n221 bye\r\n";
	$mailer = new SmtpMailerMock($greeting . $transaction, username: 'user@example.com', password: '');
	$failing = true;
	$mailer->setAccessToken(function () use (&$failing) {
		if ($failing) {
			throw new RuntimeException('token refresh failed'); // not a SendException: the caller's own I/O
		}

		return 'fresh-token';
	});

	$mail = new Message;
	$mail->setFrom('me@example.com');
	$mail->addTo('jane@example.com');
	$mail->setBody('Hi');

	Assert::exception(fn() => $mailer->send($mail), RuntimeException::class, 'token refresh failed');

	// the half-open connection must be gone: kept alive, a NOOP probe would call it healthy, the mailer
	// would skip connect(), and the server would answer MAIL FROM with a permanent 530 for evermore
	$failing = false;
	$mailer->send($mail);

	Assert::count(2, $mailer->addresses); // it dialed again and authenticated properly
	Assert::contains('AUTH XOAUTH2 ' . base64_encode("user=user@example.com\1auth=Bearer fresh-token\1\1"), $mailer->getWritten());
});


test('a token without a username is refused outright', function () use ($greeting) {
	$mailer = new SmtpMailerMock($greeting, username: '', password: '');
	$mailer->setAccessToken('ya29.token');

	Assert::exception(
		fn() => $mailer->connect(),
		SmtpException::class,
		'XOAUTH2 needs a username%a%',
	);
});


test('a server without XOAUTH2 support says so', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250-mail.example.com\r\n250 AUTH LOGIN PLAIN\r\n",
		username: 'user@example.com',
		password: '',
	);
	$mailer->setAccessToken('ya29.token');

	Assert::exception(
		fn() => $mailer->connect(),
		SmtpException::class,
		'SMTP server does not support XOAUTH2 authentication.',
	);
});
