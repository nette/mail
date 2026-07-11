<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\DkimSigner relaxed header canonicalization (RFC 6376, §3.4.2).
 */

use Nette\Mail\DkimSigner;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}


$signer = new class ('', '', '', null, ['Subject']) extends DkimSigner {
	public string $canonicalized = '';


	public function computeSignature(string $rawHeader, string $signature): string
	{
		return parent::computeSignature($rawHeader, $signature);
	}


	public function sign(string $value): string
	{
		$this->canonicalized = $value;
		return '';
	}
};


test('every WSP sequence collapses to a single space, including a lone tab', function () use ($signer) {
	$signer->computeSignature("Subject:\tHello\tthere  world \r\n", '');

	Assert::same('subject:Hello there world', explode("\r\n", $signer->canonicalized)[0]);
});


test('folded header is unfolded before canonicalization', function () use ($signer) {
	$signer->computeSignature("Subject: Hello\r\n\tthere\r\n", '');

	Assert::same('subject:Hello there', explode("\r\n", $signer->canonicalized)[0]);
});


test('header name is lowercased and WSP around the colon is removed', function () use ($signer) {
	$signer->computeSignature("Subject:   Hello   \r\n", '');

	Assert::same('subject:Hello', explode("\r\n", $signer->canonicalized)[0]);
});
