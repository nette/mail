<?php

/**
 * Test: MailExtension.
 */

use Nette\DI;
use Nette\Bridges\MailDI\MailExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';


$compiler = new DI\Compiler;
$compiler->addExtension('test', new MailExtension);

$container1 = createContainer($compiler, '
test:
	smtp: false
');
Assert::type('Nette\Mail\SendmailMailer', $container1->getService('test.mailer'));
Assert::false($container1->hasService('nette.mailer'));


$compiler = new DI\Compiler;
$compiler->addExtension('mail', new MailExtension);
$container2 = createContainer($compiler, '
mail:
	smtp: true
');
Assert::type('Nette\Mail\SmtpMailer', $container2->getService('mail.mailer'));
Assert::type($container2->getService('mail.mailer'), $container2->getService('nette.mailer'));
