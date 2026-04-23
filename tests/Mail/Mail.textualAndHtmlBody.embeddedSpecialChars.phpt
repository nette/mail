<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message - embedding images whose path contains characters
 *       previously broken by overly strict regex (spaces, parentheses).
 * @phpExtension fileinfo
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


foreach (['back ground.png', 'back(1).png'] as $filename) {
	TestMailer::$output = '';

	$mail = new Message;
	$mail->setFrom('John Doe <doe@example.com>');
	$mail->addTo('Lady Jane <jane@example.com>');
	$mail->setSubject('Hello Jane!');
	$mail->setBody('Sample text');

	$mail->setHtmlBody(
		'<img src="' . $filename . '">'
		. '<img src=\'' . $filename . '\'>'
		. '<div style="background: url(\'' . $filename . '\')">',
		__DIR__ . '/fixtures',
	);

	(new TestMailer)->send($mail);

	Assert::contains('<img src="cid:', TestMailer::$output);
	Assert::contains("<img src='cid:", TestMailer::$output);
	Assert::contains("background: url('cid:", TestMailer::$output);
	Assert::notContains('src="' . $filename . '"', TestMailer::$output);
	Assert::notContains("src='" . $filename . "'", TestMailer::$output);
	Assert::notContains("url('" . $filename . "')", TestMailer::$output);
	Assert::contains('Content-Disposition: inline; filename="' . $filename . '"', TestMailer::$output);
}
