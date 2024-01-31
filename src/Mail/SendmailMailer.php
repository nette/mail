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
	public string $commandArgs = '';
	private ?Signer $signer = null;
	// Setting an envelope address using command arg (-f $from) is allowed by default
	private bool envelopeFrom = true;


	public function setSigner(Signer $signer): static
	{
		$this->signer = $signer;
		return $this;
	}

	/**
	 * Enable/disable setting an envelope address using command arg (-f $from) 
	 */
	public function setEnvelopeFrom(bool $envelopeFrom): static
	{
		$this->evelopeFrom = $envelopeFrom;
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

		$cmd = $this->commandArgs;
		if (($this->evelopeFrom) && ($from = $mail->getFrom())) {
			$cmd .= ' -f' . key($from);
		}

		$args = [
			(string) $mail->getEncodedHeader('To'),
			(string) $mail->getEncodedHeader('Subject'),
			$parts[1],
			$parts[0],
			$cmd,
		];

		$res = Nette\Utils\Callback::invokeSafe('mail', $args, function (string $message) use (&$info): void {
			$info = ": $message";
		});
		if ($res === false) {
			throw new SendException("Unable to send email$info.");
		}
	}
}
