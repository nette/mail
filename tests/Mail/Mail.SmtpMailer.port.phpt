<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer default port per encryption.
 */

use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


function connectWith(array $args): SmtpMailerMock
{
	$mailer = new SmtpMailerMock("220 ready\r\n250 OK\r\n", ...$args);
	$mailer->connect();
	return $mailer;
}


test('no encryption defaults to port 25', function () {
	Assert::same(['mail.example.com:25'], connectWith([])->addresses);
});


test('STARTTLS defaults to the submission port 587', function () {
	// a socket pair cannot do crypto, so let the server reject EHLO: the connection is torn down
	// before STARTTLS, by which time the mailer has already dialed the address we care about
	$mailer = new SmtpMailerMock("220 ready\r\n554 go away\r\n", encryption: 'tls');
	Assert::exception(fn() => $mailer->connect(), Nette\Mail\SmtpException::class);

	Assert::same(['mail.example.com:587'], $mailer->addresses);
});


test('implicit SSL defaults to port 465 and uses the ssl:// transport', function () {
	Assert::same(['ssl://mail.example.com:465'], connectWith(['encryption' => 'ssl'])->addresses);
});


test('an explicit port always wins', function () {
	Assert::same(['mail.example.com:2525'], connectWith(['port' => 2525])->addresses);
	Assert::same(['ssl://mail.example.com:10465'], connectWith(['encryption' => 'ssl', 'port' => 10465])->addresses);
});
