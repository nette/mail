<?php

/**
 * Test: Nette\Mail\Message - textual and HTML body with embedded image.
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

$mail->setHTMLBody('
	<BODY id=1 background=background.png>
	<img src="backgroun%64.png">
	<div title=a style="background:url(background.png)">
	<style type=text/css>body { background: url(\'background.png\') } </style>
	[[background.png]]
', __DIR__ . '/fixtures');
// append automatically $mail->addEmbeddedFile('files/background.png');

$mailer = new TestMailer;
$mailer->send($mail);

Assert::matchFile(__DIR__ . '/Mail.textualAndHtmlBody.embedded.expect', TestMailer::$output);



$mail = new Message;
$mail->setHTMLBody("
	<a href='test.php?src=SOME'>some link</a>
	<script src=script.js></script>
	<div title=\"background:url(background.png)\">
	<style></style> background: url(\\'background.png\\');
	[[backgroun%64.png]]
", __DIR__ . '/fixtures');

$mailer = new TestMailer;
$mailer->send($mail);

Assert::matchFile(__DIR__ . '/Mail.textualAndHtmlBody.embedded2.expect', TestMailer::$output);
