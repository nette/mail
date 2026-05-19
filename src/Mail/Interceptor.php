<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette\Utils\Arrays;
use Nette\Utils\Validators;
use function is_array, is_string;


/**
 * Wraps a Mailer with optional redirect of outgoing emails and an $onSent event.
 */
class Interceptor implements Mailer
{
	private const LeakyHeaders = ['To', 'Cc', 'Bcc', 'Disposition-Notification-To', 'X-Confirm-Reading-To'];

	/** @var list<callable(self, Message, ?\Throwable): void> */
	public array $onSent = [];


	public function __construct(
		private Mailer $mailer,
		private ?string $redirectTo = null,
		private string $subjectPrefix = '',
	) {
		Validators::assert($redirectTo, 'email|null', 'redirect address');
	}


	public function send(Message $mail): void
	{
		$sent = $this->redirectTo !== null
			? $this->rewrite($mail, $this->redirectTo)
			: $mail;

		try {
			$this->mailer->send($sent);
		} catch (\Throwable $e) {
			Arrays::invoke($this->onSent, $this, $mail, $e);
			throw $e;
		}

		Arrays::invoke($this->onSent, $this, $mail, null);
	}


	private function rewrite(Message $mail, string $redirectTo): Message
	{
		$dolly = clone $mail;

		foreach (self::LeakyHeaders as $name) {
			$orig = $dolly->getHeader($name);
			$dolly->clearHeader($name);
			if (is_array($orig) && $orig) {
				$dolly->setHeader('X-Original-' . $name, $this->formatAddresses($orig));
			} elseif (is_string($orig) && $orig !== '') {
				$dolly->setHeader('X-Original-' . $name, $orig);
			}
		}

		$dolly->setHeader('To', [$redirectTo => null]);

		$subject = $dolly->getHeader('Subject');
		$dolly->setHeader('Subject', trim($this->subjectPrefix . ' ' . (is_string($subject) ? $subject : '')));

		return $dolly;
	}


	/** @param  array<string, ?string>  $addresses */
	private function formatAddresses(array $addresses): string
	{
		$parts = [];
		foreach ($addresses as $email => $name) {
			$parts[] = $name === null ? $email : "$name <$email>";
		}

		return implode(', ', $parts);
	}
}
