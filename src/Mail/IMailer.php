<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Mail;


/**
 * Mailer interface.
 */
interface IMailer
{

	/**
	 * Sends email.
	 * @return void
	 * @throws SendException
	 */
	function send(Message $mail);

}
