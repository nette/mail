<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\MailDI;

use Nette;


/**
 * Mail extension for Nette DI.
 */
class MailExtension extends Nette\DI\CompilerExtension
{
	public $defaults = [
		'smtp' => false,
		'host' => null,
		'port' => null,
		'username' => null,
		'password' => null,
		'secure' => null,
		'timeout' => null,
		'context' => null,
		'clientHost' => null,
		'persistent' => false,
	];


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$mailer = $builder->addDefinition($this->prefix('mailer'))
			->setType(Nette\Mail\IMailer::class);

		if (empty($config['smtp'])) {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		} else {
			$mailer->setFactory(Nette\Mail\SmtpMailer::class, [$config]);
		}

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}
}
