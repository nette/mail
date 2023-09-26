<?php

/**
 * Test: Nette\Mail\Message invalid headers.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mail = new Message;

Assert::exception(
	fn() => $mail->setHeader('', 'value'),
	InvalidArgumentException::class,
	"Header name must be non-empty alphanumeric string, '' given.",
);

Assert::exception(
	fn() => $mail->setHeader(' name', 'value'),
	InvalidArgumentException::class,
	"Header name must be non-empty alphanumeric string, ' name' given.",
);

Assert::exception(
	fn() => $mail->setHeader('n*ame', 'value'),
	InvalidArgumentException::class,
	"Header name must be non-empty alphanumeric string, 'n*ame' given.",
);
