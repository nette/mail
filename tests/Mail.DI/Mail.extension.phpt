<?php declare(strict_types=1);

/**
 * Test: MailExtension.
 */

use Nette\Bridges\MailDI\MailExtension;
use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';


test('no redirect -> bare real mailer, no Interceptor', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('test', new MailExtension);
	$container = createContainer($compiler, '
test:
	smtp: false
');
	Assert::type(Nette\Mail\SendmailMailer::class, $container->getService('test.mailer'));
	Assert::false($container->hasService('test.innerMailer'));
	Assert::false($container->hasService('test.signer'));
	Assert::false($container->hasService('nette.mailer'));
});


test('SMTP without redirect -> bare SmtpMailer', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	smtp: true
');
	Assert::type(Nette\Mail\SmtpMailer::class, $container->getService('mail.mailer'));
	Assert::false($container->hasService('mail.innerMailer'));
	Assert::type($container->getService('mail.mailer'), $container->getService('nette.mailer'));
	Assert::false($container->hasService('mail.signer'));
});


test('DKIM signer is registered', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	smtp: true
	dkim:
		privateKey: fixtures/private.key
		domain: nette.org
		selector: s
');
	Assert::type(Nette\Mail\DkimSigner::class, $container->getService('mail.signer'));
});


test('redirect (full form) -> Interceptor wraps real mailer', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	redirect:
		to: dev@example.com
		subjectPrefix: \'[debug]\'
');
	$interceptor = $container->getService('mail.mailer');
	Assert::type(Nette\Mail\Interceptor::class, $interceptor);
	Assert::type(Nette\Mail\SendmailMailer::class, $container->getService('mail.innerMailer'));
	$ref = new ReflectionObject($interceptor);
	Assert::same('dev@example.com', $ref->getProperty('redirectTo')->getValue($interceptor));
	Assert::same('[debug]', $ref->getProperty('subjectPrefix')->getValue($interceptor));
});


test('redirect: <email> shortcut form', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, '
mail:
	redirect: dev@example.com
');
	$interceptor = $container->getService('mail.mailer');
	Assert::type(Nette\Mail\Interceptor::class, $interceptor);
	$ref = new ReflectionObject($interceptor);
	Assert::same('dev@example.com', $ref->getProperty('redirectTo')->getValue($interceptor));
	Assert::same('', $ref->getProperty('subjectPrefix')->getValue($interceptor));
});


test('debug mode without redirect or debugger -> no Interceptor, no panel', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension(debugMode: true));
	$container = createContainer($compiler, '
mail:
');
	Assert::type(Nette\Mail\SendmailMailer::class, $container->getService('mail.mailer'));
	Assert::false($container->hasService('mail.panel'));
});


test('debugger: true + debug mode + Tracy\Bar -> Interceptor + panel', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension(debugMode: true));
	$container = createContainer($compiler, '
services:
	- Tracy\Bar
mail:
	debugger: true
');
	Assert::type(Nette\Mail\Interceptor::class, $container->getService('mail.mailer'));
	Assert::type(Nette\Mail\SendmailMailer::class, $container->getService('mail.innerMailer'));
	Assert::type(Nette\Bridges\MailTracy\MailPanel::class, $container->getService('mail.panel'));
});


test('redirect + debug mode + Tracy\Bar -> Interceptor (from redirect) + panel', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension(debugMode: true));
	$container = createContainer($compiler, '
services:
	- Tracy\Bar
mail:
	redirect: dev@example.com
');
	Assert::type(Nette\Mail\Interceptor::class, $container->getService('mail.mailer'));
	Assert::type(Nette\Bridges\MailTracy\MailPanel::class, $container->getService('mail.panel'));
});


test('redirect + debugger: false -> Interceptor exists, but panel does NOT', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension(debugMode: true));
	$container = createContainer($compiler, '
services:
	- Tracy\Bar
mail:
	redirect: dev@example.com
	debugger: false
');
	Assert::type(Nette\Mail\Interceptor::class, $container->getService('mail.mailer'));
	Assert::false($container->hasService('mail.panel'));
});


test('debugger: true but NOT in debug mode -> Interceptor exists (eager), but no panel', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension(debugMode: false));
	$container = createContainer($compiler, '
services:
	- Tracy\Bar
mail:
	debugger: true
');
	Assert::type(Nette\Mail\Interceptor::class, $container->getService('mail.mailer'));
	Assert::false($container->hasService('mail.panel'));
});
