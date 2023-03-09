<?php

/**
 * Test: Nette\Mail\SmtpMailer correctly handles Bcc-only message
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerTestWrapper.php';


$mail = new Message;
$mail->setFrom('Tester <tester@example.com>');
$mail->addBcc('hidden1@example.com');
$mail->addBcc('hidden2@example.com');

$mailer = new SmtpMailerTestWrapper;
$mailer->send($mail);

// check the mail was sent to all Bcc mails
[$from, $to1, $to2, $data, $body] = $mailer->getWrittenLines();
Assert::equal('MAIL FROM:<tester@example.com>', $from);
Assert::equal('RCPT TO:<hidden1@example.com>', $to1);
Assert::equal('RCPT TO:<hidden2@example.com>', $to2);
Assert::equal('DATA', $data);

// make sure no Bcc is in the body and 'To; was set to 'undisclosed-recipients'
$body = explode("\r\n", $body);
$recipientHeaders = array_values(array_filter($body, fn($line) => preg_match('/^(To|Cc|Bcc):/i', $line)));
Assert::count(1, $recipientHeaders);
Assert::equal('To: undisclosed-recipients: ;', $recipientHeaders[0]);
