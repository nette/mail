<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\MailDI;

use Nette;
use Nette\Schema\Expect;
use Tracy;


/**
 * Mail extension for Nette DI.
 *
 * @property object{
 *     debugger: bool|null,
 *     smtp: bool,
 *     host: string|null,
 *     port: int|null,
 *     username: string,
 *     password: string,
 *     secure: 'ssl'|'tls'|null,
 *     encryption: 'ssl'|'tls'|null,
 *     timeout: int,
 *     context: array<string, array<mixed>>,
 *     clientHost: string|null,
 *     persistent: bool,
 *     dkim: array{domain: string, selector: string, privateKey: string, passPhrase?: string}|null,
 *     redirect: array{to: string, subjectPrefix: string}|null,
 * } $config
 */
class MailExtension extends Nette\DI\CompilerExtension
{
	public function __construct(
		private readonly bool $debugMode = false,
	) {
	}


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'debugger' => Expect::bool()->nullable(),
			'smtp' => Expect::bool(false),
			'host' => Expect::string()->dynamic(),
			'port' => Expect::int()->dynamic(),
			'username' => Expect::string('')->dynamic(),
			'password' => Expect::string('')->dynamic(),
			'secure' => Expect::anyOf(null, 'ssl', 'tls')->dynamic(), // deprecated
			'encryption' => Expect::anyOf(null, 'ssl', 'tls')->dynamic(),
			'timeout' => Expect::int(20)->dynamic(),
			'context' => Expect::arrayOf('array')->dynamic(),
			'clientHost' => Expect::string()->dynamic(),
			'persistent' => Expect::bool(false)->dynamic(),
			'dkim' => Expect::anyOf(
				Expect::null(),
				Expect::structure([
					'domain' => Expect::string()->required()->dynamic(),
					'selector' => Expect::string()->required()->dynamic(),
					'privateKey' => Expect::string()->required(),
					'passPhrase' => Expect::string()->dynamic(),
				])->castTo('array'),
			),
			'redirect' => Expect::anyOf(
				Expect::null(),
				Expect::type('email')->transform(fn($v) => ['to' => $v]),
				Expect::structure([
					'to' => Expect::type('email')->required()->dynamic(),
					'subjectPrefix' => Expect::string('')->dynamic(),
				])->castTo('array'),
			),
		]);
	}


	public function loadConfiguration(): void
	{
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		$useInterceptor = $config->redirect || $config->debugger === true;

		$mailer = $builder->addDefinition($this->prefix($useInterceptor ? 'innerMailer' : 'mailer'))
			->setType(Nette\Mail\Mailer::class)
			->setAutowired(!$useInterceptor);

		if ($config->dkim) {
			$dkim = $config->dkim;
			$dkim['privateKey'] = Nette\Utils\FileSystem::read($dkim['privateKey']);
			unset($config->dkim);

			$signer = $builder->addDefinition($this->prefix('signer'))
				->setType(Nette\Mail\Signer::class)
				->setFactory(Nette\Mail\DkimSigner::class, $dkim);

			$mailer->addSetup('setSigner', [$signer]);
		}

		if ($config->smtp) {
			$mailer->setFactory(Nette\Mail\SmtpMailer::class, [
				'host' => $config->host ?? ini_get('SMTP'),
				'port' => isset($config->host) ? $config->port : (int) ini_get('smtp_port'),
				'username' => $config->username,
				'password' => $config->password,
				'encryption' => $config->encryption ?? $config->secure,
				'persistent' => $config->persistent,
				'timeout' => $config->timeout,
				'clientHost' => $config->clientHost,
				'streamOptions' => $config->context ?: null,
			]);

		} else {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		}

		if ($useInterceptor) {
			$builder->addDefinition($this->prefix('mailer'))
				->setType(Nette\Mail\Mailer::class)
				->setFactory(Nette\Mail\Interceptor::class, [
					'mailer' => $mailer,
					'redirectTo' => $config->redirect['to'] ?? null,
					'subjectPrefix' => $config->redirect['subjectPrefix'] ?? '',
				]);
		}

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}


	public function beforeCompile(): void
	{
		if (!$this->debugMode) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$useDebugger = $this->config->debugger || ($this->config->redirect && $this->config->debugger !== false);

		if (!$useDebugger || !$builder->getByType(Tracy\Bar::class)) {
			return;
		}

		$panel = $builder->addDefinition($this->prefix('panel'))
			->setFactory(Nette\Bridges\MailTracy\MailPanel::class)
			->addSetup('@' . Tracy\Bar::class . '::addPanel', ['@self']);

		$mailer = $builder->getDefinition($this->prefix('mailer'));
		\assert($mailer instanceof Nette\DI\Definitions\ServiceDefinition);
		$mailer->addSetup('$onSent[]', [[$panel, 'recordSent']]);
	}
}
