<?php

/**
 * Test: Nette\Mail\Message - HTML title.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message;

$mail->setHTMLBody('
<title>Hello
</title>
<p>Content</p>');

Assert::match('Hello ', $mail->getSubject());

Assert::match('<p>Content</p>', $mail->getHTMLBody());

Assert::match('Content', $mail->getBody());
