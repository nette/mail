<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use function array_filter, array_merge, array_search, base64_encode, explode, extension_loaded, hash, implode, in_array, ksort, openssl_pkey_get_private, openssl_sign, pack, preg_match, preg_replace, rtrim, str_contains, str_replace, strlen, strtolower, time, trim;


/**
 * Signs email messages using DKIM (DomainKeys Identified Mail).
 */
class DkimSigner implements Signer
{
	private const DefaultSignHeaders = [
		'From',
		'To',
		'Date',
		'Subject',
		'Message-ID',
		'X-Mailer',
		'Content-Type',
		'List-Unsubscribe',
		'List-Unsubscribe-Post',
	];

	private const DkimSignature = 'DKIM-Signature';
	private const AlgorithmRsa = 'rsa-sha256';
	private const AlgorithmEd25519 = 'ed25519-sha256';
	private const Ed25519SeedLength = 32;
	private const Ed25519SecretKeyLength = 64;

	private readonly string $algorithm;


	/** @throws Nette\NotSupportedException */
	public function __construct(
		private readonly string $domain,
		private readonly string $selector,
		#[\SensitiveParameter]
		private readonly string $privateKey,
		#[\SensitiveParameter]
		private readonly ?string $passPhrase = null,
		/** @var list<string> */
		private readonly array $signHeaders = self::DefaultSignHeaders,
		/** @var list<string> */
		private readonly array $oversignHeaders = [],
	) {
		$this->algorithm = self::detectAlgorithm($privateKey);
		$extension = $this->algorithm === self::AlgorithmEd25519 ? 'sodium' : 'openssl';
		if (!extension_loaded($extension)) {
			throw new Nette\NotSupportedException("DkimSigner requires PHP extension $extension which is not loaded.");
		}
	}


	/**
	 * Ed25519 keys (RFC 8463) are raw base64, RSA keys are PEM-encoded.
	 */
	private static function detectAlgorithm(#[\SensitiveParameter] string $privateKey): string
	{
		$raw = base64_decode(trim($privateKey), strict: true);
		return $raw !== false && in_array(strlen($raw), [self::Ed25519SeedLength, self::Ed25519SecretKeyLength], strict: true)
			? self::AlgorithmEd25519
			: self::AlgorithmRsa;
	}


	/**
	 * Returns the signed message as a string with the DKIM-Signature header prepended.
	 * @throws SignException
	 */
	public function generateSignedMessage(Message $message): string
	{
		$message = $message->build();

		if (preg_match("~(.*?\r\n\r\n)(.*)~s", $message->getEncodedMessage(), $parts)) {
			[, $header, $body] = $parts;

			return rtrim($header, "\r\n") . "\r\n" . $this->getSignature($message, $header, $this->normalizeNewLines($body)) . "\r\n\r\n" . $body;
		}

		throw new SignException('Malformed email');
	}


	/**
	 * Builds the DKIM-Signature header value.
	 */
	protected function getSignature(Message $message, string $header, string $body): string
	{
		$parts = [];
		foreach (
			[
				'v' => '1',
				'a' => $this->algorithm,
				'q' => 'dns/txt',
				's' => $this->selector,
				't' => $this->getTime(),
				'c' => 'relaxed/simple',
				'h' => implode(':', $this->getSignedHeaders($message)),
				'd' => $this->domain,
				'bh' => $this->computeBodyHash($body),
				'b' => '',
			] as $key => $value
		) {
			$parts[] = $key . '=' . $value;
		}

		return $this->computeSignature($header, self::DkimSignature . ': ' . implode('; ', $parts));
	}


	/**
	 * Canonicalizes the selected headers, signs them, and returns the complete DKIM-Signature header string.
	 * Uses the 'relaxed' header canonicalization of RFC 6376, §3.4.2.
	 */
	protected function computeSignature(string $rawHeader, string $signature): string
	{
		// header names are case-insensitive; the message may spell them differently than the configuration
		$selectedHeaders = array_map(strtolower(...), array_merge($this->getHashedHeaders(), [self::DkimSignature]));

		$rawHeader = preg_replace("/\r\n[ \t]+/", ' ', rtrim($rawHeader, "\r\n") . "\r\n" . $signature); // unfold

		$parts = [];
		foreach (explode("\r\n", $rawHeader) as $header) {
			if (str_contains($header, ':')) {
				[$heading, $value] = explode(':', $header, 2);

				if (($index = array_search(strtolower(trim($heading)), $selectedHeaders, strict: true)) !== false) {
					$parts[$index] =
						trim(strtolower($heading), " \t") . ':' .
						trim(preg_replace('/[ \t]+/', ' ', $value), " \t"); // every WSP sequence collapses to a single space
				}
			}
		}

		ksort($parts);

		return $signature . $this->sign(implode("\r\n", $parts));
	}


	/**
	 * Signs the value with the private key and returns the base64-encoded signature.
	 * @throws SignException
	 */
	protected function sign(string $value): string
	{
		return match ($this->algorithm) {
			self::AlgorithmEd25519 => $this->signEd25519($value),
			default => $this->signRsa($value),
		};
	}


	/** @throws SignException */
	private function signRsa(string $value): string
	{
		$privateKey = openssl_pkey_get_private($this->privateKey, $this->passPhrase);
		if (!$privateKey) {
			throw new SignException('Invalid private key');
		}

		if (openssl_sign($value, $signature, $privateKey, 'sha256WithRSAEncryption')) {
			return base64_encode($signature);
		}

		return '';
	}


	/** @throws SignException */
	private function signEd25519(string $value): string
	{
		$raw = (string) base64_decode(trim($this->privateKey), strict: true);
		$secretKey = match (strlen($raw)) {
			self::Ed25519SeedLength => sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($raw)),
			self::Ed25519SecretKeyLength => $raw,
			default => throw new SignException('Invalid private key'),
		};

		return base64_encode(sodium_crypto_sign_detached($value, $secretKey));
	}


	/**
	 * Computes the base64-encoded SHA-256 hash of the canonicalized body.
	 */
	protected function computeBodyHash(string $body): string
	{
		return base64_encode(pack('H*', hash('sha256', $body)));
	}


	/**
	 * Normalizes line endings to CRLF and ensures the body ends with a single CRLF.
	 */
	protected function normalizeNewLines(string $s): string
	{
		$s = str_replace(["\r\n", "\n"], "\r", $s);
		$s = str_replace("\r", "\r\n", $s);
		return rtrim($s, "\r\n") . "\r\n";
	}


	/** @return list<string> */
	private function getHashedHeaders(): array
	{
		$names = $this->signHeaders;
		foreach ($this->oversignHeaders as $name) {
			if (!in_array(strtolower($name), array_map(strtolower(...), $names), strict: true)) {
				$names[] = $name;
			}
		}

		return $names;
	}


	/**
	 * Returns the headers to list in the h= tag: those present in the message, plus one extra mention
	 * for each oversigned one (RFC 6376, §3.7).
	 * @return list<string>
	 */
	protected function getSignedHeaders(Message $message): array
	{
		$present = array_values(array_filter(
			$this->getHashedHeaders(),
			fn($name) => $message->getHeader($name) !== null,
		));

		return array_merge($present, $this->oversignHeaders);
	}


	protected function getTime(): int
	{
		return time();
	}
}
