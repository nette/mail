<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;


/**
 * Send failed exception
 *
 * @author Jan Dvořák
 */
interface SendFailedException
{
}


/**
 * Sendmail exception.
 *
 * @author Jan Dvořák
 */
class SendmailException extends Nette\InvalidStateException implements SendFailedException
{
}


/**
 * SMTP mailer exception.
 *
 * @author     David Grudl
 */
class SmtpException extends \Exception implements SendFailedException
{
}
