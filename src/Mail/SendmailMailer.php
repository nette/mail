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
class SendmailMailer implements IMailer
{
	use Nette\SmartObject;

	/** @var string|null */
	public $commandArgs;

	/** @var ISigner|null */
	public $signer;


	/**
	 * @param ISigner $signer
	 * @return self
	 */
	public function setSigner(ISigner $signer): self
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
		$tmp = clone $mail;
		$tmp->setHeader('Subject', null);
		$tmp->setHeader('To', null);

		if ($this->signer instanceof ISigner) {
			$data = $this->signer->generateSignedMessage($tmp);
		} else {
			$data = $tmp->generateMessage();
		}

		$parts = explode(Message::EOL . Message::EOL, $data, 2);

		$args = [
			str_replace(Message::EOL, PHP_EOL, $mail->getEncodedHeader('To')),
			str_replace(Message::EOL, PHP_EOL, $mail->getEncodedHeader('Subject')),
			str_replace(Message::EOL, PHP_EOL, $parts[1]),
			str_replace(Message::EOL, PHP_EOL, $parts[0]),
		];
		if ($this->commandArgs) {
			$args[] = $this->commandArgs;
		}
		$res = Nette\Utils\Callback::invokeSafe('mail', $args, function ($message) use (&$info) {
			$info = ": $message";
		});
		if ($res === false) {
			throw new SendException("Unable to send email$info.");
		}
	}
}
