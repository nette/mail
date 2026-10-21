<?php declare(strict_types=1);

/**
 * Test: MailExtension with an OAuth access token.
 */

use Nette\Bridges\MailDI\MailExtension;
use Nette\DI;
use Nette\Mail\SmtpMailer;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';


class TokenProvider
{
	public int $calls = 0;


	public function getAccessToken(): string
	{
		return 'token-' . ++$this->calls;
	}
}


function getAccessToken(SmtpMailer $mailer): ?Closure
{
	return (new ReflectionProperty($mailer, 'accessToken'))->getValue($mailer);
}


test('string token', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	smtp: true
	accessToken: secret
');
	$mailer = $container->getService('mail.mailer');
	Assert::type(SmtpMailer::class, $mailer);
	Assert::same('secret', getAccessToken($mailer)());
});


test('no token -> none is set', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	smtp: true
');
	Assert::null(getAccessToken($container->getService('mail.mailer')));
});


test('first-class callable of a service is resolved on every call', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
services:
	tokens: TokenProvider
mail:
	smtp: true
	accessToken: @tokens::getAccessToken(...)
');
	$token = getAccessToken($container->getService('mail.mailer'));
	Assert::same('token-1', $token());
	Assert::same('token-2', $token());
	Assert::same(2, $container->getByType(TokenProvider::class)->calls);
});


test('redirect -> token is set on the inner SmtpMailer', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	smtp: true
	accessToken: secret
	redirect: dev@example.com
');
	Assert::type(Nette\Mail\Interceptor::class, $container->getService('mail.mailer'));
	Assert::same('secret', getAccessToken($container->getService('mail.innerMailer'))());
});


test('token without the SMTP transport is a configuration error', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	Assert::exception(
		fn() => createContainer($compiler, '
mail:
	accessToken: secret
'),
		DI\InvalidConfigurationException::class,
		"Option 'mail\u{a0}›\u{a0}accessToken' requires the SMTP transport, i.e. 'smtp: true' without 'file'.",
	);

	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	Assert::exception(
		fn() => createContainer($compiler, '
mail:
	smtp: true
	file: mails
	accessToken: secret
'),
		DI\InvalidConfigurationException::class,
		"Option 'mail\u{a0}›\u{a0}accessToken' requires the SMTP transport, i.e. 'smtp: true' without 'file'.",
	);
});
