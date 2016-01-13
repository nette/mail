<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\MailDI;

use Nette;


/**
 * Mail extension for Nette DI.
 */
class MailExtension extends Nette\DI\CompilerExtension
{

	public $defaults = [
		'smtp' => FALSE,
		'host' => NULL,
		'port' => NULL,
		'username' => NULL,
		'password' => NULL,
		'secure' => NULL,
		'timeout' => NULL,
	];


	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$mailer = $container->addDefinition($this->prefix('mailer'))
			->setClass(Nette\Mail\IMailer::class);

		if (empty($config['smtp'])) {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		} else {
			$mailer->setFactory(Nette\Mail\SmtpMailer::class);
			$config['encryption'] = & $config['secure'];
			if (!isset($config['host'])) {
				$config['host'] = ini_get('SMTP');
				$config['port'] = (int) ini_get('smtp_port');
			}
			$config['port'] = empty($config['port'])
				? (isset($config['secure']) && $config['secure'] === 'ssl' ? 465 : 25)
				: (int) $config['port'];

			foreach (['host', 'port', 'username', 'password', 'encryption', 'timeout', 'persistent'] as $item) {
				if (isset($config[$item])) {
					$mailer->addSetup('$' . $item, [$config[$item]]);
				}
			}
		}

		if ($this->name === 'mail') {
			$container->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}

}
