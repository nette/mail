<?php

/**
 * Common code for Mail test cases.
 */

declare(strict_types=1);

use Nette\Mail\IMailer;
use Nette\Mail\Message;


class TestMailer implements IMailer
{
	public static $output;


	public function send(Message $mail): void
	{
		self::$output = $mail->generateMessage();
	}
}
