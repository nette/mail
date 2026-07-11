<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\DkimSigner sign.
 */

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$privateKey = file_get_contents(__DIR__ . '/fixtures/private.key');

$mail = new Message;
$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');
$mail->setBody('Příliš žluťoučký kůň');

$signer = new class ('nette.org', 'selector', $privateKey, null, ['From', 'To', 'Subject']) extends DkimSigner {
	protected function getTime(): int
	{
		return 0;
	}
};

Assert::match(<<<'EOD'
	MIME-Version: 1.0
	X-Mailer: Nette Framework
	Date: %a%
	From: John Doe <doe@example.com>
	To: Lady Jane <jane@example.com>
	Subject: Hello Jane!
	Message-ID: <%a%@%a%>
	Content-Type: text/plain; charset=UTF-8
	Content-Transfer-Encoding: 8bit
	DKIM-Signature: v=1; a=rsa-sha256; q=dns/txt; s=selector; t=0; c=relaxed/simple; h=From:To:Subject; d=nette.org; bh=ajG6YIACaHQVmGzBb/7kmuYS2aRqla4IYr5sTMwVP7k=; b=GQ0mUUir+Dr962CoYDRX0Led3p+zAB/pzdvWldHuUrgEZPtjPEWVJSzb9zlgxTVLhp1wdEBuV0VxGDdr0R0/sR3Cve6DEGlXAdIB8vfDxKjPYXvvd8ihV4ze/4UU/ntiHTVkGCwgwunZqJtNqqLfOgfSqmtiogmUeHDIzue7F18=

	Příliš žluťoučký kůň
	EOD, $signer->generateSignedMessage($mail));
