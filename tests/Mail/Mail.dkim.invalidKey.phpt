<?php

/**
 * Test: Nette\Mail\DkimSigner invalid private key.
 */

declare(strict_types=1);

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Nette\Mail\SignException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$signer = new DkimSigner('', '', '');

$mail = new Message;
$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');
$mail->setBody('Příliš žluťoučký kůň');

Assert::exception(
	fn() => $signer->generateSignedMessage($mail),
	SignException::class,
);
