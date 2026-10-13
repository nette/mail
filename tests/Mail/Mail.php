<?php declare(strict_types=1);

/**
 * Common code for Mail test cases.
 */

use Nette\Mail\{Mailer, Message};


class TestMailer implements Mailer
{
	public static $output;


	public function send(Message $mail): void
	{
		self::$output = $mail->generateMessage();
	}
}
