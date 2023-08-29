<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;


class FallbackMailer implements Mailer
{
	/** @var callable[]  function (FallbackMailer $sender, SendException $e, Mailer $mailer, Message $mail): void */
	public array $onFailure = [];

	/** @var Mailer[] */
	private array $mailers;

	private int $retryCount;

	/** in miliseconds */
	private int $retryWaitTime;


	/**
	 * @param Mailer[]  $mailers
	 */
	public function __construct(array $mailers, int $retryCount = 3, int $retryWaitTime = 1000)
	{
		$this->mailers = $mailers;
		$this->retryCount = $retryCount;
		$this->retryWaitTime = $retryWaitTime;
	}


	/**
	 * Sends email.
	 * @throws FallbackMailerException
	 */
	public function send(Message $mail): void
	{
		if (!$this->mailers) {
			throw new Nette\InvalidArgumentException('At least one mailer must be provided.');
		}

		$failures = [];
		for ($i = 0; $i < $this->retryCount; $i++) {
			if ($i > 0) {
				usleep($this->retryWaitTime * 1000);
			}

			foreach ($this->mailers as $mailer) {
				try {
					$mailer->send($mail);
					return;

				} catch (SendException $e) {
					$failures[] = $e;
					Nette\Utils\Arrays::invoke($this->onFailure, $this, $e, $mailer, $mail);
				}
			}
		}

		$e = new FallbackMailerException('All mailers failed to send the message.');
		$e->failures = $failures;
		throw $e;
	}


	public function addMailer(Mailer $mailer): static
	{
		$this->mailers[] = $mailer;
		return $this;
	}
}
