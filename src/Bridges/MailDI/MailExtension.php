<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\MailDI;

use Nette;
use Nette\Schema\Expect;


/**
 * Mail extension for Nette DI.
 */
class MailExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'smtp' => Expect::bool(false),
			'host' => Expect::string()->dynamic(),
			'port' => Expect::int()->dynamic(),
			'username' => Expect::string()->dynamic(),
			'password' => Expect::string()->dynamic(),
			'secure' => Expect::anyOf(null, 'ssl', 'tls')->dynamic(),
			'timeout' => Expect::int()->dynamic(),
			'context' => Expect::arrayOf('array')->dynamic(),
			'clientHost' => Expect::string()->dynamic(),
			'persistent' => Expect::bool(false)->dynamic(),
		])->castTo('array');
	}


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$mailer = $builder->addDefinition($this->prefix('mailer'))
			->setType(Nette\Mail\IMailer::class);

		if ($this->config['smtp']) {
			$mailer->setFactory(Nette\Mail\SmtpMailer::class, [$this->config]);
		} else {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		}

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}
}
