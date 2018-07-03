<?php

/**
 * Test: SendmailMailer with `disable_functions = mail`.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

if (ini_get('disable_functions') !== 'mail') {
	Tester\Environment::skip('mail() is enabled');
}

Assert::exception(function () {
	$sendmailMailer = new \Nette\Mail\SendmailMailer();
	$message = new Message();
	$sendmailMailer->send($message);
}, \Nette\Mail\SendException::class, 'Unable to send email: mail() has been disabled for security reasons.');

Assert::true(true);
