<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer persistent connection handling.
 */

use Nette\Mail\Message;
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


const Greeting = "220 ready\r\n250 OK\r\n";
const Transaction = "250 sender\r\n250 recipient\r\n354 go ahead\r\n250 queued\r\n";


test('a live persistent connection is reused, and probed with NOOP first', function () {
	$mailer = new SmtpMailerMock(
		Greeting . Transaction . "250 noop\r\n" . Transaction,
		persistent: true,
	);

	$mailer->send(createMail());
	$mailer->send(createMail());

	Assert::count(1, $mailer->addresses); // connected once, no reconnect
	Assert::contains('NOOP', $mailer->getWrittenLines());
});


test('a persistent connection dropped by the server is re-established', function () {
	$mailer = new SmtpMailerMock(Greeting . Transaction, persistent: true);

	$mailer->send(createMail());
	Assert::count(1, $mailer->addresses);

	$mailer->closeServerSide(); // the server hangs up on the idle session

	$mailer->send(createMail());

	Assert::count(2, $mailer->addresses); // reconnected instead of writing into a dead socket
	Assert::same('EHLO localhost', $mailer->getWrittenLines()[0]); // a full fresh handshake
});


test('a non-persistent mailer connects per message and does not probe', function () {
	$mailer = new SmtpMailerMock(Greeting . Transaction . "221 bye\r\n");

	$mailer->send(createMail());
	$mailer->send(createMail());

	Assert::count(2, $mailer->addresses);
	Assert::notContains('NOOP', $mailer->getWrittenLines());
});
