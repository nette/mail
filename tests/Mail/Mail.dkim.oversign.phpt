<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\DkimSigner oversigning (RFC 6376, §8.15).
 */

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$privateKey = file_get_contents(__DIR__ . '/fixtures/private.key');


function createSigner(string $privateKey, array $signHeaders, array $oversignHeaders): DkimSigner
{
	return new class ('nette.org', 'selector', $privateKey, null, $signHeaders, $oversignHeaders) extends DkimSigner {
		public string $signedData = '';


		protected function sign(string $value): string
		{
			$this->signedData = $value;
			return parent::sign($value);
		}
	};
}


function createMail(): Message
{
	$mail = new Message;
	$mail->setFrom('John Doe <doe@example.com>');
	$mail->addTo('Lady Jane <jane@example.com>');
	$mail->setSubject('Hello Jane!');
	$mail->setBody('Hello');
	return $mail;
}


test('no oversigning by default', function () use ($privateKey) {
	$signer = createSigner($privateKey, ['From', 'To', 'Subject'], []);

	Assert::match('%A%; h=From:To:Subject; %A%', $signer->generateSignedMessage(createMail()));
});


test('an oversigned header is listed in h= one extra time', function () use ($privateKey) {
	$signer = createSigner($privateKey, ['From', 'To', 'Subject'], ['From']);

	Assert::match('%A%; h=From:To:Subject:From; %A%', $signer->generateSignedMessage(createMail()));
});


test('a header absent from the message can be oversigned too', function () use ($privateKey) {
	$signer = createSigner($privateKey, ['From', 'To'], ['Cc']);
	$mail = createMail();

	Assert::null($mail->getHeader('Cc'));
	Assert::match('%A%; h=From:To:Cc; %A%', $signer->generateSignedMessage($mail));
});


test('an oversigned header present in the message is hashed, not just named in h=', function () use ($privateKey) {
	// Cc is oversigned but is not among the signed headers, and the message carries one. A receiver
	// hashes every header h= names, so failing to hash it here would yield an unverifiable signature.
	$signer = createSigner($privateKey, ['From', 'To'], ['Cc']);
	$mail = createMail()->addCc('cc@example.com');

	Assert::match('%A%; h=From:To:Cc:Cc; %A%', $signer->generateSignedMessage($mail));

	$hashed = array_map(fn($line) => explode(':', $line, 2)[0], explode("\r\n", $signer->signedData));
	Assert::same(['from', 'to', 'cc', 'dkim-signature'], $hashed);
});


test('every name in h= is hashed exactly as often as it is named, minus the oversign extra', function () use ($privateKey) {
	$signer = createSigner($privateKey, ['From', 'To', 'Subject'], ['From', 'Cc']);
	$mail = createMail()->addCc('cc@example.com');

	$message = $signer->generateSignedMessage($mail);
	Assert::match('%A%; h=From:To:Subject:Cc:From:Cc; %A%', $message);

	$hashed = array_map(fn($line) => explode(':', $line, 2)[0], explode("\r\n", $signer->signedData));
	Assert::same(['from', 'to', 'subject', 'cc', 'dkim-signature'], $hashed);
});


test('a receiver can verify the signature: it hashes exactly what h= told it to', function () use ($privateKey) {
	$signer = createSigner($privateKey, ['From', 'To', 'Subject'], ['From', 'Cc']);
	$mail = createMail()->addCc('cc@example.com');

	$message = $signer->generateSignedMessage($mail);

	// rebuild the hash input the way a receiver does: walk h= left to right, taking each named header
	// from the message in turn, and treating a name with no instance left as the null input
	preg_match('#DKIM-Signature: ([^\r\n]+)#', $message, $m);
	preg_match('#h=([^;]+);#', $m[1], $h);
	[$rawHeaders] = explode("\r\n\r\n", $message, 2);

	$instances = [];
	foreach (explode("\r\n", $rawHeaders) as $line) {
		if (preg_match('#^([\w-]+):(.*)$#', $line, $p)) {
			$instances[strtolower($p[1])][] = trim(preg_replace('/[ \t]+/', ' ', $p[2]));
		}
	}

	$parts = [];
	foreach (explode(':', $h[1]) as $name) {
		$name = strtolower($name);
		if ($instances[$name] ?? false) {
			$parts[] = $name . ':' . array_shift($instances[$name]); // an instance is consumed per mention
		} // no instance left -> null input, contributes nothing
	}

	$parts[] = 'dkim-signature:' . trim(preg_replace('/[ \t]+/', ' ', preg_replace('#b=[^;]*$#', 'b=', $m[1])));

	Assert::same($signer->signedData, implode("\r\n", $parts));

	// and the signature really does verify against that data
	$publicKey = openssl_pkey_get_details(openssl_pkey_get_private($privateKey))['key'];
	preg_match('#b=([^\r\n;]+)$#', $m[1], $b);
	Assert::same(1, openssl_verify(
		$signer->signedData,
		base64_decode(trim($b[1]), strict: true),
		$publicKey,
		'sha256WithRSAEncryption',
	));
});


test('oversigning adds nothing to the hashed data: an absent header is the null input', function () use ($privateKey) {
	$plain = createSigner($privateKey, ['From', 'To', 'Subject'], []);
	$oversigned = createSigner($privateKey, ['From', 'To', 'Subject'], ['From']);

	$mail = createMail();
	$plain->generateSignedMessage($mail);
	$oversigned->generateSignedMessage($mail);

	// the canonicalized headers differ only in the DKIM-Signature line (its h= tag)
	$strip = fn(string $data) => preg_replace('#^dkim-signature:.*$#m', '', $data);

	Assert::same($strip($plain->signedData), $strip($oversigned->signedData));
	Assert::notContains("from:John Doe <doe@example.com>\r\nfrom:", $oversigned->signedData);
});
