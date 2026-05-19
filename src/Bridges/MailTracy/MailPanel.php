<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\MailTracy;

use Nette;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy;


/**
 * Tracy Bar panel showing emails sent during the request.
 * Subscribes to Interceptor::$onSent.
 */
class MailPanel implements Tracy\IBarPanel
{
	public int $maxMessages = 20;

	/** @var list<array{from: ?string, to: ?string, cc: ?string, bcc: ?string, subject: ?string, error: ?\Throwable}> */
	private array $messages = [];
	private int $count = 0;


	public function recordSent(Mailer $sender, Message $mail, ?\Throwable $error): void
	{
		$this->count++;
		if (count($this->messages) < $this->maxMessages) {
			$this->messages[] = [
				'from' => self::formatAddresses($mail->getHeader('From')),
				'to' => self::formatAddresses($mail->getHeader('To')),
				'cc' => self::formatAddresses($mail->getHeader('Cc')),
				'bcc' => self::formatAddresses($mail->getHeader('Bcc')),
				'subject' => $mail->getSubject(),
				'error' => $error,
			];
		}
	}


	/** @param  string|array<string, ?string>|null  $value */
	private static function formatAddresses(string|array|null $value): ?string
	{
		if (!is_array($value)) {
			return $value;
		}

		$parts = [];
		foreach ($value as $email => $name) {
			$parts[] = $name === null ? $email : "$name <$email>";
		}

		return $parts ? implode(', ', $parts) : null;
	}


	public function getTab(): ?string
	{
		if (!$this->count) {
			return null;
		}

		return Nette\Utils\Helpers::capture(function () {
			$count = $this->count;
			require __DIR__ . '/dist/tab.phtml';
		});
	}


	public function getPanel(): ?string
	{
		if (!$this->count) {
			return null;
		}

		return Nette\Utils\Helpers::capture(function () {
			$messages = $this->messages;
			$count = $this->count;
			require __DIR__ . '/dist/panel.phtml';
		});
	}
}
