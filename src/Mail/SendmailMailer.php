<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;


/**
 * Sends emails via the PHP internal mail() function.
 */
class SendmailMailer implements Mailer
{
	use Nette\SmartObject;

	public ?string $commandArgs = null;
	private ?Signer $signer = null;


	public function setSigner(Signer $signer): static
	{
		$this->signer = $signer;
		return $this;
	}


	/**
	 * Sends email.
	 * @throws SendException
	 */
	public function send(Message $mail): void
	{
		if (!function_exists('mail')) {
			throw new SendException('Unable to send email: mail() has been disabled.');
		}

		$tmp = clone $mail;
		$tmp->setHeader('Subject', null);
		$tmp->setHeader('To', null);

		$data = $this->signer
			? $this->signer->generateSignedMessage($tmp)
			: $tmp->generateMessage();
		$parts = explode(Message::EOL . Message::EOL, $data, 2);

		$args = [
			str_replace(Message::EOL, PHP_EOL, (string) $mail->getEncodedHeader('To')),
			str_replace(Message::EOL, PHP_EOL, (string) $mail->getEncodedHeader('Subject')),
			str_replace(Message::EOL, PHP_EOL, $parts[1]),
			$parts[0],
		];

		if ($from = $mail->getFrom()) {
			$args[] = '-f' . key($from);
		}

		if ($this->commandArgs) {
			$args[] = $this->commandArgs;
		}

		$res = Nette\Utils\Callback::invokeSafe('mail', $args, function (string $message) use (&$info): void {
			$info = ": $message";
		});
		if ($res === false) {
			throw new SendException("Unable to send email$info.");
		}
	}
}
