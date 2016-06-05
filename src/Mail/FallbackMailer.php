<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use Nette\InvalidArgumentException;


class FallbackMailer implements IMailer
{
	use Nette\SmartObject;

	/** @var callable[]  function (FallbackMailer $sender, SendException $e, IMailer $mailer, Message $mail) */
	public $onFailure;

	/** @var IMailer[] */
	private $mailers;

	/** @var int */
	private $retryCount;

	/** @var int in miliseconds */
	private $retryWaitTime;


	/**
	 * @param IMailer[]
	 * @param int
	 * @param int in miliseconds
	 */
	public function __construct(array $mailers, $retryCount = 3, $retryWaitTime = 1000)
	{
		if (!$mailers) {
			throw new InvalidArgumentException('At least one mailer must be provided.');
		}

		$this->mailers = $mailers;
		$this->retryCount = $retryCount;
		$this->retryWaitTime = $retryWaitTime;
	}


	/**
	 * Sends email.
	 * @return void
	 * @throws FallbackMailerException
	 */
	public function send(Message $mail)
	{
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
					$this->onFailure($this, $e, $mailer, $mail);
				}
			}
		}

		$e = new FallbackMailerException('All mailers failed to send the message.', 0, $failures[0]);
		$e->failures = $failures;
		throw $e;
	}


	/**
	 * @return IMailer[]
	 */
	public function getMailers()
	{
		return $this->mailers;
	}


	/**
	 * @param  IMailer[]
	 * @return self
	 */
	public function addMailer(IMailer $mailer)
	{
		$this->mailers[] = $mailer;
	}


	/**
	 * @return int
	 */
	public function getRetryCount()
	{
		return $this->retryCount;
	}


	/**
	 * @param int
	 */
	public function setRetryCount($retryCount)
	{
		$this->retryCount = $retryCount;
	}


	/**
	 * @return int in miliseconds
	 */
	public function getRetryWaitTime()
	{
		return $this->retryWaitTime;
	}


	/**
	 * @param int in miliseconds
	 */
	public function setRetryWaitTime($retryWaitTime)
	{
		$this->retryWaitTime = $retryWaitTime;
	}

}
