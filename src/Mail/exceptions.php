<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;


/**
 * Failed to send the email.
 */
class SendException extends Nette\InvalidStateException
{
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		private readonly bool $permanent = false,
	) {
		parent::__construct($message, $code, $previous);
	}


	/**
	 * Tells whether the same attempt is worth repeating. A dropped connection may well work next
	 * time; a message the server has already refused will be refused again just as fast.
	 */
	public function isPermanent(): bool
	{
		return $this->permanent;
	}
}


/**
 * Failed to communicate with the SMTP server.
 */
class SmtpException extends SendException
{
	/**
	 * Creates the exception from a server reply. A 5xx reply is a permanent negative completion,
	 * a 4xx one is transient (RFC 5321, §4.2.1).
	 */
	public static function fromReply(string $message, string $response): self
	{
		$code = (int) $response;
		return new self($message, permanent: $code >= 500 && $code < 600);
	}
}


/**
 * All configured mailers failed to send the email.
 */
class FallbackMailerException extends SendException
{
	/** @var list<SendException> */
	public array $failures;
}


/**
 * Failed to create or verify the email signature.
 */
class SignException extends SendException
{
}
