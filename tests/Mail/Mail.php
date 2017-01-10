<?php

/**
 * Common code for Mail test cases.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Nette\Mail\IMailer;


class TestMailer implements IMailer
{
	public static $output;

	function send(Message $mail)
	{
		self::$output = $mail->generateMessage();
	}

}
