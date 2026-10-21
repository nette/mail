<?php declare(strict_types=1);

/**
 * Test: MailExtension with the file transport.
 */

use Nette\Bridges\MailDI\MailExtension;
use Nette\DI;
use Nette\Mail\{FileMailer, Interceptor, Mailer, Message};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';


$tempDir = sys_get_temp_dir() . '/Mail.extension.file.' . getmypid();
Tester\Helpers::purge($tempDir);


function createMail(): Message
{
	$mail = new Message;
	$mail->setFrom('doe@example.com');
	$mail->addTo('jane@example.com');
	$mail->setSubject('Hello Jane!');
	return $mail;
}


test('file: -> FileMailer writing into the directory', function () use ($tempDir) {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, "
mail:
	file: '$tempDir/plain'
");
	$mailer = $container->getByType(Mailer::class);
	Assert::type(FileMailer::class, $mailer);
	Assert::same($mailer, $container->getService('mail.mailer'));
	Assert::false($container->hasService('mail.innerMailer'));

	$mailer->send(createMail());
	Assert::contains('Subject: Hello Jane!', file_get_contents(glob("$tempDir/plain/*.eml")[0]));
});


test('file: takes precedence over smtp: true', function () use ($tempDir) {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, "
mail:
	smtp: true
	file: '$tempDir/smtp'
");
	Assert::type(FileMailer::class, $container->getService('mail.mailer'));
});


test('file: + redirect -> Interceptor wraps FileMailer', function () use ($tempDir) {
	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, "
mail:
	file: '$tempDir/redirect'
	redirect: dev@example.com
");
	Assert::type(Interceptor::class, $container->getService('mail.mailer'));
	Assert::type(FileMailer::class, $container->getService('mail.innerMailer'));

	$container->getService('mail.mailer')->send(createMail());
	Assert::contains('To: dev@example.com', file_get_contents(glob("$tempDir/redirect/*.eml")[0]));
});


test('file: + dkim -> FileMailer signs the message', function () use ($tempDir) {
	if (!extension_loaded('openssl')) {
		return;
	}

	$compiler = new DI\Compiler;
	$compiler->addExtension('mail', new MailExtension);
	$container = createContainer($compiler, "
mail:
	file: '$tempDir/dkim'
	dkim:
		privateKey: fixtures/private.key
		domain: nette.org
		selector: s
");
	$container->getService('mail.mailer')->send(createMail());
	Assert::contains('DKIM-Signature: v=1;', file_get_contents(glob("$tempDir/dkim/*.eml")[0]));
});


Tester\Helpers::purge($tempDir);
rmdir($tempDir);
