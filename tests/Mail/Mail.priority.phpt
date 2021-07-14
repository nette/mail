<?php

/**
 * Test: Nette\Mail\Message - Priority.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message;

$mail->setPriority(2);

Assert::same(2, $mail->getPriority());
