<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Bridges\MailDI;

use Nette;


/**
 * Nette Framework Mail services.
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
		if (isset($config['nette']['mailer'])) { // back compatibility
			$config = Nette\DI\Config\Helpers::merge($config['nette']['mailer'], $this->defaults);
			trigger_error("nette.mailer configuration section is deprecated, use {$this->name} section instead.", E_USER_DEPRECATED);
		} else {
			$config = $this->getConfig($this->defaults);
		}

		$this->validate($config, $this->defaults, $this->name);

		$mailer = $container->addDefinition('nette.mailer')
			->setClass('Nette\Mail\IMailer');

		if (empty($config['smtp'])) {
			$mailer->setFactory('Nette\Mail\SendmailMailer');
		} else {
			$mailer->setFactory('Nette\Mail\SmtpMailer', array($config));
		}
	}


	private function validate(array $config, array $expected, $name)
	{
		if ($extra = array_diff_key($config, $expected)) {
			$extra = implode(", $name.", array_keys($extra));
			throw new Nette\InvalidStateException("Unknown option $name.$extra.");
		}
	}

}
