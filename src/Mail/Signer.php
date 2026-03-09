<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;


/**
 * Signs email messages.
 */
interface Signer
{
	/**
	 * Returns the signed email message as a string.
	 * @throws SignException
	 */
	public function generateSignedMessage(Message $message): string;
}
