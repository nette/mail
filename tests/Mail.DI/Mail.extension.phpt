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


Assert::error(function() use ($compiler, & $container2) {
	$container2 = createContainer($compiler, '
		nette:
			mailer:
				smtp: true
	');
}, 'E_USER_DEPRECATED', "Configuration section 'nette.mailer' is deprecated, use section 'test' and service 'test.mailer' instead.");

Assert::type( 'Nette\Mail\SmtpMailer', $container2->getService('nette.mailer') );
