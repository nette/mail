<?php

/**
 * @phpIni disable_functions=mail
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$sendmailMailer = new Nette\Mail\SendmailMailer;
	$message = new Message;
	$sendmailMailer->send($message);
}, Nette\Mail\SendException::class, 'Unable to send email: mail() has been disabled.');
