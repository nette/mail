<?php

/**
 * Test: Nette\Mail\DkimSigner headers filter.
 */

declare(strict_types=1);

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$signer = new class ('', '', '', null, ['From', 'To', 'Date', 'Subject', 'Message-ID', 'X-Mailer', 'Content-Type']) extends DkimSigner {
	public function getSignedHeaders(Message $message): array
	{
		return parent::getSignedHeaders($message);
	}
};

$mail = new Message;
$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');
$mail->setBody('Příliš žluťoučký kůň');

Assert::equal([
	0 => 'From',
	1 => 'To',
	2 => 'Date',
	3 => 'Subject',
	5 => 'X-Mailer',
], $signer->getSignedHeaders($mail));
