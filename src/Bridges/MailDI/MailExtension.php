<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Bridges\MailDI;

use Nette;


/**
 * Mail extension for Nette DI.
 *
 * @author     David Grudl
 * @author     Petr Morávek
 */
class MailExtension extends Nette\DI\CompilerExtension
{

	public $defaults = array(
		'smtp' => FALSE,
		'host' => NULL,
		'port' => NULL,
		'username' => NULL,
		'password' => NULL,
		'secure' => NULL,
		'timeout' => NULL,
	);


	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$mailer = $container->addDefinition($this->prefix('mailer'))
			->setClass('Nette\Mail\IMailer');

		if (empty($config['smtp'])) {
			$mailer->setFactory('Nette\Mail\SendmailMailer');
		} else {
			$mailer->setFactory('Nette\Mail\SmtpMailer', array($config));
		}

		if ($this->name === 'mail') {
			$container->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}

}
