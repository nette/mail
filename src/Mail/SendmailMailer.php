<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;


/**
 * Sends emails via the PHP internal mail() function.
 */
class SendmailMailer implements Mailer
{
	public string $commandArgs = '';
	private ?Signer $signer = null;
	private bool $envelopeSender = true;


	public function setSigner(Signer $signer): static
	{
		$this->signer = $signer;
		return $this;
	}


	/**
	 * Sets whether to use the envelope sender (-f option) in the mail command.
	 */
	public function setEnvelopeSender(bool $state = true): static
	{
		$this->envelopeSender = $state;
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

		$data = $this->signer
			? $this->signer->generateSignedMessage($mail)
			: $mail->generateMessage();
		[$headers, $body] = explode(Message::EOL . Message::EOL, $data, 2);

		// mail() adds 'To' and 'Subject' itself, remove them from the header block (after DKIM signing)
		$lines = preg_split('#\r\n(?![ \t])#', $headers);
		$lines = array_filter($lines, fn($line) => !preg_match('#^(?:To|Subject):#', $line));
		$headers = implode(Message::EOL, $lines);

		$cmd = $this->commandArgs;
		if ($this->envelopeSender && ($from = $mail->getFrom())) {
			$cmd .= ' -f' . key($from);
		}

		$this->invokeMail(
			(string) $mail->getEncodedHeader('To'),
			(string) $mail->getEncodedHeader('Subject'),
			$body,
			$headers,
			$cmd,
		);
	}


	/** @throws SendException */
	protected function invokeMail(string $to, string $subject, string $body, string $headers, string $cmd): void
	{
		$info = '';
		$res = Nette\Utils\Callback::invokeSafe('mail', [$to, $subject, $body, $headers, $cmd], function (string $message) use (&$info): void {
			$info = ": $message";
		});
		if ($res === false) {
			throw new SendException("Unable to send email$info.");
		}
	}
}
