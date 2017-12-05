<?php

/**
 * Test: Nette\Mail\Message - textual and HTML body with embedded image and attachment.
 * @phpExtension fileinfo
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mail = new Message;

$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');

$mail->setBody('Sample text');

$mail->setHTMLBody('<b>Sample text</b> <img src="background.png">', __DIR__ . '/fixtures');
// append automatically $mail->addEmbeddedFile('files/background.png');

$mail->addAttachment('fixtures/example.zip');

$mailer = new TestMailer;
$mailer->send($mail);

Assert::matchFile(__DIR__ . '/Mail.textualAndHtmlBody.embedded.attachment.expect', TestMailer::$output);
