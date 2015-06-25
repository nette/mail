<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;


/**
 * Mailer interface.
 *
 * @author     David Grudl
 */
interface IMailer
{

	/**
	 * Sends email.
	 * @return void
	 *
	 * @throws Nette\Mail\SendFailedException
	 */
	function send(Message $mail);

}
