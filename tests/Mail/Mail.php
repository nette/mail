<?php

/**
 * Common code for Mail test cases.
 */

use Nette\Mail\IMailer;
use Nette\Mail\Message;


class TestMailer implements IMailer
{
	public static $output;


	public function send(Message $mail)
	{
		self::$output = $mail->generateMessage();
	}
}
