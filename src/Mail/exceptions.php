<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;


/**
 * Failed to send the email.
 */
class SendException extends Nette\InvalidStateException
{
}


/**
 * Failed to communicate with the SMTP server.
 */
class SmtpException extends SendException
{
}


/**
 * All configured mailers failed to send the email.
 */
class FallbackMailerException extends SendException
{
	/** @var SendException[] */
	public array $failures;
}


/**
 * Failed to create or verify the email signature.
 */
class SignException extends SendException
{
}
