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
		'smtp' => FALSE,
		'host' => NULL,
		'port' => NULL,
		'username' => NULL,
		'password' => NULL,
		'secure' => NULL,
		'timeout' => NULL,
		'message' => [
			'mime-version' => NULL,
			'x-mailer' => NULL,
		],
	];


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$mailer = $builder->addDefinition($this->prefix('mailer'))
			->setClass(Nette\Mail\IMailer::class);

		if (empty($config['smtp'])) {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		} else {
			$mailer->setFactory(Nette\Mail\SmtpMailer::class, [$config]);
		}

		if ($config['message']['mime-version'] === FALSE) {
			unset(Nette\Mail\Message::$defaultHeaders['MIME-Version']);
		} else {
			if ($config['message']['mime-version'] !== NULL) {
				Nette\Mail\Message::$defaultHeaders['MIME-Version'] = $config['message']['mime-version'];
			}
		}

		if ($config['message']['x-mailer'] === FALSE) {
			unset(Nette\Mail\Message::$defaultHeaders['X-Mailer']);
		} else {
			if ($config['message']['x-mailer'] !== NULL) {
				Nette\Mail\Message::$defaultHeaders['X-Mailer'] = $config['message']['x-mailer'];
			}
		}

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}

}
