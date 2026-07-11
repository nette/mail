<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\DkimSigner Ed25519 signing (RFC 8463).
 */

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('sodium')) {
	Tester\Environment::skip('Sodium not installed');
}

// the Ed25519 test key from RFC 8463, §A.3 (base64-encoded 32-byte seed)
$privateKey = 'nWGxne/9WmC6hEr0kuwsxERJxWl7MmkZcDusAxyuf2A=';
$publicKey = '11qYAYKxCrfVS/7TyWQHOg7hcvPapiMlrwIaaPcHURo=';


function createMail(): Message
{
	$mail = new Message;
	$mail->setFrom('John Doe <doe@example.com>');
	$mail->addTo('Lady Jane <jane@example.com>');
	$mail->setSubject('Hello Jane!');
	$mail->setBody('Příliš žluťoučký kůň');
	return $mail;
}


test('a raw base64 key selects the ed25519-sha256 algorithm', function () use ($privateKey) {
	$signer = new DkimSigner('nette.org', 'selector', $privateKey, null, ['From', 'To', 'Subject']);

	Assert::match(
		'%A%DKIM-Signature: v=1; a=ed25519-sha256; %A%',
		$signer->generateSignedMessage(createMail()),
	);
});


test('the signature verifies against the matching public key', function () use ($privateKey, $publicKey) {
	$signed = null;
	$signer = new class ('nette.org', 'selector', $privateKey, null, ['From', 'To', 'Subject']) extends DkimSigner {
		public string $signedData = '';


		protected function sign(string $value): string
		{
			$this->signedData = $value;
			return parent::sign($value);
		}
	};

	$message = $signer->generateSignedMessage(createMail());

	// pull the b= tag out of the generated DKIM-Signature header
	Assert::true((bool) preg_match('#DKIM-Signature:.*; b=([^\r\n]+)#', $message, $m));
	$signature = base64_decode($m[1], strict: true);

	Assert::true(sodium_crypto_sign_verify_detached(
		$signature,
		$signer->signedData,
		base64_decode($publicKey, strict: true),
	));
});


test('the seed and the full secret key produce the same signature', function () use ($privateKey) {
	$secretKey = base64_encode(sodium_crypto_sign_secretkey(
		sodium_crypto_sign_seed_keypair(base64_decode($privateKey, strict: true)),
	));

	$sign = fn(string $key) => (new class ('nette.org', 'selector', $key, null, ['From', 'To', 'Subject']) extends DkimSigner {
		protected function getTime(): int
		{
			return 0;
		}
	})->generateSignedMessage(createMail());

	// Message-ID and Date differ per message, so compare just the b= tag
	$extract = fn(string $mail) => preg_match('#; b=([^\r\n]+)#', $mail, $m) ? $m[1] : null;

	Assert::same($extract($sign($privateKey)), $extract($sign($secretKey)));
});


test('a PEM key still selects RSA', function () {
	if (!extension_loaded('openssl')) {
		return;
	}

	$signer = new DkimSigner('nette.org', 'selector', file_get_contents(__DIR__ . '/fixtures/private.key'), null, ['From']);

	Assert::match('%A%DKIM-Signature: v=1; a=rsa-sha256; %A%', $signer->generateSignedMessage(createMail()));
});
