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

		$config = $this->compiler->getConfig();
		$prefix = isset($config[$this->name]) ? $this->name : 'nette';
		if ($oldSection = !isset($config[$this->name]) && isset($config['nette']['mailer'])) {
			$config = Nette\DI\Config\Helpers::merge($config['nette']['mailer'], $this->defaults);
			//trigger_error("Configuration section 'nette.mailer' is deprecated, use section '$this->name' and service '$this->name.mailer' instead.", E_USER_DEPRECATED);
		} else {
			$config = $this->getConfig($this->defaults);
		}

		$this->validateConfig($this->defaults, $config, $oldSection ? 'nette.mailer' : $this->name);

		$mailer = $container->addDefinition($prefix . '.mailer')
			->setClass('Nette\Mail\IMailer');

		if (empty($config['smtp'])) {
			$mailer->setFactory('Nette\Mail\SendmailMailer');
		} else {
			$mailer->setFactory('Nette\Mail\SmtpMailer', array($config));
		}
	}

}
