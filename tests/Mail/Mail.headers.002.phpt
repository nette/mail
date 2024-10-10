<?php

/**
 * Test: Nette\Mail\Message valid headers.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mail = new Message;

$mail->setFrom('Kdo uteče, obědvá <doe@example.com>');

$mail->addTo('Lady Jane <jane@example.com>');
$mail->addCc('jane@example.info');
$mail->addBcc('bcc@example.com');
$mail->addReplyTo('reply@example.com');
$mail->setReturnPath('doe@example.com');

$mail->setSubject('Hello Jane!');
$mail->setPriority(Message::High);

$mail->setHeader('X-Gmail-Label', 'love');

$mailer = new TestMailer;
$mailer->send($mail);

Assert::match(<<<'EOD'
	MIME-Version: 1.0
	X-Mailer: Nette Framework
	Date: %a%
	From: =?UTF-8?B?IktkbyB1dGXEjWUsIG9ixJtkdsOhIg==?= <doe@example.com>
	To: Lady Jane <jane@example.com>
	Cc: jane@example.info
	Bcc: bcc@example.com
	Reply-To: reply@example.com
	Return-Path: doe@example.com
	Subject: Hello Jane!
	X-Priority: 1
	X-Gmail-Label: love
	Message-ID: <%a%@%a%>
	Content-Type: text/plain; charset=UTF-8
	Content-Transfer-Encoding: 7bit
	EOD, TestMailer::$output);

Assert::match('"Kdo uteče, obědvá"', iconv_mime_decode('=?UTF-8?B?IktkbyB1dGXEjWUsIG9ixJtkdsOhIg==?='));
