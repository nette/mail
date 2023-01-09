<?php

/**
 * Test: MailExtension.
 */

declare(strict_types=1);

use Nette\Bridges\MailDI\MailExtension;
use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/include.php';


$compiler = new DI\Compiler;
$compiler->addExtension('test', new MailExtension);

$container1 = createContainer($compiler, '
test:
	smtp: false
');
Assert::type(Nette\Mail\SendmailMailer::class, $container1->getService('test.mailer'));
Assert::false($container1->hasService('test.signer'));
Assert::false($container1->hasService('nette.mailer'));


$compiler = new DI\Compiler;
$compiler->addExtension('mail', new MailExtension);
$container2 = createContainer($compiler, '
mail:
	smtp: true
');
Assert::type(Nette\Mail\SmtpMailer::class, $container2->getService('mail.mailer'));
Assert::type($container2->getService('mail.mailer'), $container2->getService('nette.mailer'));
Assert::false($container2->hasService('mail.signer'));


$compiler = new DI\Compiler;
$compiler->addExtension('mail', new MailExtension);
$container3 = createContainer($compiler, '
mail:
	smtp: true
	dkim:
		privateKey: fixtures/private.key
		domain: nette.org
		selector: s

');
Assert::type(Nette\Mail\DkimSigner::class, $container3->getService('mail.signer'));
