<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SendmailMailer strips To/Subject from header block after DKIM signs them.
 */

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}


$captured = new class extends SendmailMailer {
	public array $args = [];


	protected function invokeMail(string $to, string $subject, string $body, string $headers, string $cmd): void
	{
		$this->args = compact('to', 'subject', 'body', 'headers', 'cmd');
	}
};


$privateKey = file_get_contents(__DIR__ . '/fixtures/private.key');
$signer = new class ('nette.org', 'selector', $privateKey) extends DkimSigner {
	protected function getTime(): int
	{
		return 0;
	}
};
$captured->setSigner($signer);

$mail = new Message;
$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');
$mail->setBody('Příliš žluťoučký kůň');

$captured->send($mail);

// To and Subject go through mail()'s dedicated arguments
Assert::same('Lady Jane <jane@example.com>', $captured->args['to']);
Assert::same('Hello Jane!', $captured->args['subject']);

// ...so they must NOT be in the additional-headers block (would otherwise cause duplicate headers)
$headerLines = preg_split('#\r\n(?![ \t])#', $captured->args['headers']);
$names = array_map(fn($line) => strtolower(explode(':', $line, 2)[0]), $headerLines);
Assert::contains('from', $names);
Assert::contains('dkim-signature', $names);
Assert::notContains('to', $names);
Assert::notContains('subject', $names);

// But DKIM signed them: both must appear in the h= tag (this is the regression guard for #99)
Assert::match('%A%DKIM-Signature:%a%h=From:To:Date:Subject:Message-ID:X-Mailer:Content-Type;%a%', $captured->args['headers']);
