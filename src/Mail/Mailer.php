<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;


/**
 * Sends email messages.
 */
interface Mailer
{
	/**
	 * Sends email.
	 * @throws SendException
	 */
	function send(Message $mail): void;
}


class_alias(Mailer::class, IMailer::class);
if (false) {
	/** @deprecated use Nette\Mail\Mailer */
	interface IMailer extends Mailer
	{
	}
}
