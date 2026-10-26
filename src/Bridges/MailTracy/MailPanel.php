<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\MailTracy;

use Nette;
use Nette\Mail\{Mailer, Message, MimePart};
use Tracy;
use function count, is_array, is_string, strlen;


/**
 * Tracy Bar panel showing emails sent during the request.
 * Subscribes to Interceptor::$onSent.
 */
class MailPanel implements Tracy\IBarPanel
{
	public int $maxMessages = 20;

	/** @var list<array{from: ?string, to: ?string, cc: ?string, bcc: ?string, subject: ?string, html: ?string, text: string, attachments: list<array{name: ?string, type: ?string, size: int}>, error: ?\Throwable}> */
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
				'html' => self::inlineEmbeddedFiles($mail),
				'text' => $mail->getBody(),
				'attachments' => array_map(self::describeAttachment(...), $mail->getAttachments()),
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


	/**
	 * Returns the HTML body with cid: references replaced by data: URIs of the embedded files.
	 */
	private static function inlineEmbeddedFiles(Message $mail): ?string
	{
		$html = $mail->getHtmlBody();
		if ($html === '') {
			return null;
		}

		$uris = [];
		foreach ($mail->getEmbeddedFiles() as $part) {
			$id = $part->getHeader('Content-ID');
			$type = $part->getHeader('Content-Type');
			if (is_string($id)) {
				$uris['cid:' . trim($id, '<>')] = 'data:' . (is_string($type) ? explode(';', $type)[0] : 'application/octet-stream')
					. ';base64,' . base64_encode($part->getBody());
			}
		}

		return strtr($html, $uris);
	}


	/** @return array{name: ?string, type: ?string, size: int} */
	private static function describeAttachment(MimePart $part): array
	{
		$disposition = $part->getHeader('Content-Disposition');
		$type = $part->getHeader('Content-Type');
		return [
			'name' => is_string($disposition) && preg_match('#; ?filename="(.*)"$#D', $disposition, $m) ? stripslashes($m[1]) : null,
			'type' => is_string($type) ? $type : null,
			'size' => strlen($part->getBody()),
		];
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
