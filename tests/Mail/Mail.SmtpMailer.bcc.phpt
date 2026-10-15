<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer correctly handles Bcc-only message
 */

use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


$mail = new Message;
$mail->setFrom('Tester <tester@example.com>');
$mail->addBcc('hidden1@example.com');
$mail->addBcc('hidden2@example.com');

$mailer = new SmtpMailerMock(
	"220 ready\r\n250 OK\r\n250 sender\r\n250 recipient\r\n250 recipient\r\n354 go ahead\r\n250 queued\r\n221 bye\r\n",
);
$mailer->send($mail);

// check the mail was sent to all Bcc mails
$lines = $mailer->getWrittenLines();
Assert::same(
	['EHLO localhost', 'MAIL FROM:<tester@example.com>', 'RCPT TO:<hidden1@example.com>', 'RCPT TO:<hidden2@example.com>', 'DATA'],
	array_slice($lines, 0, 5),
);

// make sure no Bcc is in the body and 'To' was set to 'undisclosed-recipients'
$body = array_slice($lines, 5);
$recipientHeaders = array_values(array_filter($body, fn($line) => preg_match('/^(To|Cc|Bcc):/i', $line)));
Assert::count(1, $recipientHeaders);
Assert::same('To: undisclosed-recipients: ;', $recipientHeaders[0]);
