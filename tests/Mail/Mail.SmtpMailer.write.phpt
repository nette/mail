<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer write failures.
 */

use Nette\Mail\Message;
use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


function createMail(): Message
{
	$mail = new Message;
	$mail->setFrom('tester@example.com');
	$mail->addTo('jane@example.com');
	$mail->setBody('Hello');
	return $mail;
}


test('a full conversation is written in the right order', function () {
	$mailer = new SmtpMailerMock(
		"220 ready\r\n250 OK\r\n250 sender ok\r\n250 recipient ok\r\n354 go ahead\r\n250 queued\r\n221 bye\r\n",
	);
	$mailer->send(createMail());

	$lines = $mailer->getWrittenLines();
	Assert::same('EHLO localhost', $lines[0]);
	Assert::same('MAIL FROM:<tester@example.com>', $lines[1]);
	Assert::same('RCPT TO:<jane@example.com>', $lines[2]);
	Assert::same('DATA', $lines[3]);
	Assert::same('.', $lines[count($lines) - 2]);
	Assert::same('QUIT', $lines[count($lines) - 1]);
});


test('a failed write raises SmtpException instead of a PHP warning', function () {
	$mailer = new SmtpMailerMock("220 ready\r\n250 OK\r\n");
	$mailer->connect();
	$mailer->closeServerSide();

	// no expected code, so nothing is read back: this can only fail on the write itself.
	// The payload is far larger than the send buffer, so it cannot vanish into it unnoticed.
	Assert::exception(
		fn() => $mailer->write(str_repeat('x', 10_000_000)),
		SmtpException::class,
		'Unable to write to the SMTP server%a%',
	);
});


test('writing without a connection raises SmtpException', function () {
	$mailer = new SmtpMailerMock;

	Assert::exception(
		fn() => $mailer->write('NOOP'),
		SmtpException::class,
		'Not connected to SMTP server.',
	);
});
