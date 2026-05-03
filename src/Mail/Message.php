<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use Nette\Utils\Strings;
use function addcslashes, basename, date, finfo_buffer, finfo_open, is_array, is_numeric, is_string, ltrim, php_uname, preg_match, preg_replace, str_replace, strcasecmp, stripslashes, substr;


/**
 * Represents an email message with support for HTML body, attachments, and embedded files.
 *
 * @property-deprecated   string $subject
 * @property-deprecated   string $htmlBody
 */
class Message extends MimePart
{
	/** Priority */
	public const
		High = 1,
		Normal = 3,
		Low = 5;

	#[\Deprecated('use Message::High')]
	public const HIGH = self::High;

	#[\Deprecated('use Message::Normal')]
	public const NORMAL = self::Normal;

	#[\Deprecated('use Message::Low')]
	public const LOW = self::Low;

	/** @var array<string, string> */
	public static array $defaultHeaders = [
		'MIME-Version' => '1.0',
		'X-Mailer' => 'Nette Framework',
	];

	/** @var list<MimePart> */
	private array $attachments = [];

	/** @var array<MimePart> */
	private array $inlines = [];
	private string $htmlBody = '';


	public function __construct()
	{
		foreach (static::$defaultHeaders as $name => $value) {
			$this->setHeader($name, $value);
		}

		$this->setHeader('Date', date('r'));
	}


	/**
	 * Sets the sender of the message. Email or format "John Doe" <doe@example.com>
	 */
	public function setFrom(string $email, ?string $name = null): static
	{
		$this->setHeader('From', $this->formatEmail($email, $name));
		return $this;
	}


	/**
	 * Returns the sender of the message.
	 * @return ?array<string, ?string>
	 */
	public function getFrom(): ?array
	{
		$value = $this->getHeader('From');
		return is_array($value) ? $value : null;
	}


	/**
	 * Adds the reply-to address. Email or format "John Doe" <doe@example.com>
	 */
	public function addReplyTo(string $email, ?string $name = null): static
	{
		$this->setHeader('Reply-To', $this->formatEmail($email, $name), append: true);
		return $this;
	}


	public function setSubject(string $subject): static
	{
		$this->setHeader('Subject', $subject);
		return $this;
	}


	public function getSubject(): ?string
	{
		$value = $this->getHeader('Subject');
		return is_string($value) ? $value : null;
	}


	/**
	 * Adds email recipient. Email or format "John Doe" <doe@example.com>
	 */
	public function addTo(string $email, ?string $name = null): static // addRecipient()
	{
		$this->setHeader('To', $this->formatEmail($email, $name), append: true);
		return $this;
	}


	/**
	 * Adds carbon copy email recipient. Email or format "John Doe" <doe@example.com>
	 */
	public function addCc(string $email, ?string $name = null): static
	{
		$this->setHeader('Cc', $this->formatEmail($email, $name), append: true);
		return $this;
	}


	/**
	 * Adds blind carbon copy email recipient. Email or format "John Doe" <doe@example.com>
	 */
	public function addBcc(string $email, ?string $name = null): static
	{
		$this->setHeader('Bcc', $this->formatEmail($email, $name), append: true);
		return $this;
	}


	/**
	 * Formats recipient email.
	 * @return array<string, ?string>
	 */
	private function formatEmail(string $email, ?string $name = null): array
	{
		if (!$name && preg_match('#^(.+) +<(.*)>$#D', $email, $matches)) {
			[, $name, $email] = $matches;
			$name = stripslashes($name);
			$tmp = substr($name, 1, -1);
			if ($name === '"' . $tmp . '"') {
				$name = $tmp;
			}
		}

		return [$email => $name];
	}


	public function setReturnPath(string $email): static
	{
		$this->setHeader('Return-Path', $email);
		return $this;
	}


	public function getReturnPath(): ?string
	{
		$value = $this->getHeader('Return-Path');
		return is_string($value) ? $value : null;
	}


	public function setPriority(int $priority): static
	{
		$this->setHeader('X-Priority', (string) $priority);
		return $this;
	}


	public function getPriority(): ?int
	{
		$priority = $this->getHeader('X-Priority');
		return is_numeric($priority) ? (int) $priority : null;
	}


	/**
	 * Sets HTML body. If $basePath is provided, local images referenced in the HTML
	 * are automatically embedded as inline attachments with their src rewritten to cid: URIs.
	 * Also sets the subject from the HTML <title> if not already set, and auto-generates a plain-text alternative.
	 */
	public function setHtmlBody(string $html, ?string $basePath = null): static
	{
		$composer = new HtmlComposer($html);
		if ($basePath) {
			$composer->embedImages($basePath);
		}
		$composer->applyTo($this);
		return $this;
	}


	public function getHtmlBody(): string
	{
		return $this->htmlBody;
	}


	/** @internal used by HtmlComposer */
	public function setRawHtmlBody(string $html): void
	{
		$this->htmlBody = ltrim(str_replace("\r", '', $html), "\n");
	}


	/**
	 * Adds an embedded (inline) file. If $content is null, the file is read from disk.
	 * In that case $file is the path; otherwise $file is used as the filename.
	 */
	public function addEmbeddedFile(string $file, ?string $content = null, ?string $contentType = null): MimePart
	{
		return $this->inlines[$file] = $this->createAttachment($file, $content, $contentType, 'inline')
			->setHeader('Content-ID', $this->getRandomId());
	}


	/**
	 * Adds a pre-built MIME part as an inline (embedded) attachment.
	 */
	public function addInlinePart(MimePart $part): static
	{
		$this->inlines[] = $part;
		return $this;
	}


	/**
	 * Adds an attachment. If $content is null, the file is read from disk.
	 * In that case $file is the path; otherwise $file is used as the filename.
	 */
	public function addAttachment(string $file, ?string $content = null, ?string $contentType = null): MimePart
	{
		return $this->attachments[] = $this->createAttachment($file, $content, $contentType, 'attachment');
	}


	/**
	 * @return list<MimePart>
	 */
	public function getAttachments(): array
	{
		return $this->attachments;
	}


	/**
	 * Creates file MIME part.
	 */
	private function createAttachment(
		string $file,
		?string $content,
		?string $contentType,
		string $disposition,
	): MimePart
	{
		$part = new MimePart;
		if ($content === null) {
			$content = Nette\Utils\FileSystem::read($file);
			$file = Strings::fixEncoding(basename($file));
		}

		if (!$contentType) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$contentType = $finfo ? finfo_buffer($finfo, $content) : false;
			$contentType = $contentType ?: 'application/octet-stream';
		}

		if (!strcasecmp($contentType, 'message/rfc822')) { // not allowed for attached files
			$contentType = 'application/octet-stream';
		} elseif (!strcasecmp($contentType, 'image/svg')) { // Troublesome for some mailers...
			$contentType = 'image/svg+xml';
		}

		$part->setBody($content);
		$part->setContentType($contentType);
		$part->setEncoding(preg_match('#(multipart|message)/#A', $contentType) ? self::Encoding8Bit : self::EncodingBase64);
		$part->setHeader('Content-Disposition', $disposition . '; filename="' . addcslashes($file, '"\\') . '"');
		return $part;
	}


	/********************* building and sending ****************d*g**/


	/**
	 * Returns encoded message.
	 */
	public function generateMessage(): string
	{
		return $this->build()->getEncodedMessage();
	}


	/**
	 * Builds email. Does not modify itself, but returns a new object.
	 */
	public function build(): static
	{
		$mail = clone $this;
		$mail->setHeader('Message-ID', $mail->getHeader('Message-ID') ?? $this->getRandomId());

		$cursor = $mail;
		if ($mail->attachments) {
			$tmp = $cursor->setContentType('multipart/mixed');
			$cursor = $cursor->addPart();
			foreach ($mail->attachments as $value) {
				$tmp->addPart($value);
			}
		}

		if ($mail->htmlBody !== '') {
			$tmp = $cursor->setContentType('multipart/alternative');
			$cursor = $cursor->addPart();
			$alt = $tmp->addPart();
			if ($mail->inlines) {
				$tmp = $alt->setContentType('multipart/related');
				$alt = $alt->addPart();
				foreach ($mail->inlines as $value) {
					$tmp->addPart($value);
				}
			}

			$alt->setContentType('text/html', 'UTF-8')
				->setEncoding(preg_match('#[^\n]{990}#', $mail->htmlBody)
					? self::EncodingQuotedPrintable
					: (preg_match('#[\x80-\xFF]#', $mail->htmlBody) ? self::Encoding8Bit : self::Encoding7Bit))
				->setBody($mail->htmlBody);
		}

		$text = $mail->getBody();
		$mail->setBody('');
		$cursor->setContentType('text/plain', 'UTF-8')
			->setEncoding(preg_match('#[^\n]{990}#', $text)
				? self::EncodingQuotedPrintable
				: (preg_match('#[\x80-\xFF]#', $text) ? self::Encoding8Bit : self::Encoding7Bit))
			->setBody($text);

		return $mail;
	}


	private function getRandomId(): string
	{
		return '<' . Nette\Utils\Random::generate() . '@'
			. preg_replace('#[^\w.-]+#', '', $_SERVER['HTTP_HOST'] ?? php_uname('n'))
			. '>';
	}
}
