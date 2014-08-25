<?php

/**
 * Test: MailExtension.
 */

use Nette\DI,
	Nette\Bridges\MailDI\MailExtension,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';



$compiler = new DI\Compiler;
$compiler->addExtension('nette', new FooExtension);
$compiler->addExtension('test', new MailExtension);

$container1 = createContainer($compiler, '
nette:
	mailer:
		smtp: true

test:
	smtp: false
');
Assert::type( 'Nette\Mail\SendmailMailer', $container1->getService('test.mailer') );

$container2 = createContainer($compiler, '
	nette:
		mailer:
			smtp: true
');
Assert::type( 'Nette\Mail\SmtpMailer', $container2->getService('nette.mailer') );
