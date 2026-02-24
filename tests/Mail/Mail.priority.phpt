<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message - Priority.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message;

Assert::null($mail->getPriority());

$mail->setPriority(2);

Assert::same(2, $mail->getPriority());
