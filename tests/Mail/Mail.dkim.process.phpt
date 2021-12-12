<?php

/**
 * Test: Nette\Mail\DkimSigner check process.
 */

declare(strict_types=1);

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$signer = new class ([], ['From', 'To', 'Subject', 'X-Mailer', 'Content-Type']) extends DkimSigner {
	public function computeSignature(string $rawHeader, string $signature): string
	{
		$headers = parent::computeSignature($rawHeader, $signature);

		Assert::match(
			'DKIM-Signature: v=1; a=rsa-sha256; q=dns/txt; l=31; s=; t=%i%; c=relaxed/simple; h=From:To:Subject:X-Mailer:Content-Type; d=; bh=ajG6YIACaHQVmGzBb/7kmuYS2aRqla4IYr5sTMwVP7k=; b=',
			$headers
		);

		return $headers;
	}


	public function sign(string $value): string
	{
		$headers = <<<'EOT'
from:John Doe <doe@example.com>
to:Lady Jane <jane@example.com>
subject:Hello Jane!
x-mailer:Nette Framework
content-type:text/plain; charset=UTF-8
dkim-signature:v=1; a=rsa-sha256; q=dns/txt; l=31; s=; t=%i%; c=relaxed/simple; h=From:To:Subject:X-Mailer:Content-Type; d=; bh=ajG6YIACaHQVmGzBb/7kmuYS2aRqla4IYr5sTMwVP7k=; b=
EOT;

		Assert::match($headers, $value);

		return '';
	}


	public function computeBodyHash(string $body): string
	{
		return parent::computeBodyHash($body);
	}


	public function normalizeNewLines(string $string): string
	{
		return parent::normalizeNewLines($string);
	}


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

$signer->generateSignedMessage($mail);


/* ------- Compute body hash ------- */
Assert::same(
	'abFfM+PwY0xL0+O1BKLPEFMxG2LPt7VnNuT0ID67bPo=',
	$signer->computeBodyHash('<b><span>Příliš </span> <a href="http://green.example.com">žluťoučký</a> " <br><a href=\'http://horse.example.com\'>kůň</a></b>')
);

Assert::same(
	's2Jb3VCMpa9+zZIo1utaYU2hUO1CYARjhuvfZK53qaw=',
	$signer->computeBodyHash('I L@ve Nette <3')
);


/* ------- Get signed headers ------- */
Assert::same("hello\r\n Nette\r\nTracy\r\n   \r\n<3\r\n", $signer->normalizeNewLines("hello\n Nette\rTracy\r\n   \r<3"));
