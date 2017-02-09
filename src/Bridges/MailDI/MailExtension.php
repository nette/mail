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
			"defaultHeaders" => [],
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

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');
		$config = $this->validateConfig($this->defaults);

		foreach ((array) $config["message"]["defaultHeaders"] as $name => $value) {
			if ($value === FALSE) {
				if (isset(Nette\Mail\Message::$defaultHeaders[$name])) {
					$initialize->addBody('unset(Nette\Mail\Message::$defaultHeaders[?]);', [$name]);
				} else {
					throw new Nette\InvalidArgumentException('Default mail message header "' . $name . '" does not exist.');
				}
			} else {
				$initialize->addBody('Nette\Mail\Message::$defaultHeaders[?] = ?;', [$name, $value]);
			}
		}
	}
}
