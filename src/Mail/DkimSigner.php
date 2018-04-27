<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2018 Lukáš Piják
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;


class DkimSigner implements ISigner
{
	use Nette\SmartObject;

	private const DEFAULT_SIGN_HEADERS = [
		'From',
		'To',
		'Date',
		'Subject',
		'Message-ID',
		'X-Mailer',
		'Content-Type',
	];

	private const DKIM_SIGNATURE = 'DKIM-Signature';

	/** @var string */
	private $domain;

	/** @var array */
	private $signHeaders;

	/** @var string */
	private $selector;

	/** @var string */
	private $privateKey;

	/** @var string */
	private $passPhrase;

	/** @var bool */
	private $testMode = false;


	/**
	 * DkimSigner constructor.
	 * @param array $settings
	 * @param array $signHeaders
	 * @throws SignException
	 */
	public function __construct(array $settings, array $signHeaders = self::DEFAULT_SIGN_HEADERS)
	{
		if (extension_loaded('openssl')) {
			$this->domain = $settings['domain'] ?? '';
			$this->selector = $settings['selector'] ?? '';
			$this->privateKey = $settings['privateKey'] ?? '';
			$this->passPhrase = $settings['passPhrase'] ?? '';
			$this->testMode = isset($settings['testMode']) ? (bool) $settings['testMode'] : false;
			$this->signHeaders = count($signHeaders) > 0 ? $signHeaders : self::DEFAULT_SIGN_HEADERS;
		} else {
			throw new SignException('OpenSSL not installed');
		}
	}


	/**
	 * @param Message $message
	 * @return string
	 * @throws SignException
	 */
	public function generateSignedMessage(Message $message): string
	{
		if (preg_match("~(.*?\r\n\r\n)(.*)~s", $message->generateMessage(), $parts)) {
			[$_, $header, $body] = $parts;

			return rtrim($header, "\r\n") . "\r\n" . $this->getSignature($message, $header, $this->normalizeNewLines($body)) . "\r\n\r\n" . $body;
		}
		throw new SignException('Malformed email');
	}


	/**
	 * @param Message $message
	 * @param string $header
	 * @param string $body
	 * @return string
	 */
	protected function getSignature(Message $message, string $header, string $body): string
	{
		$parts = [];

		foreach (
			[
				'v' => '1',
				'a' => 'rsa-sha256',
				'q' => 'dns/txt',
				'l' => strlen($body),
				's' => $this->selector,
				't' => $this->testMode ? 0 : time(),
				//'x' => time() + 60,
				'c' => 'relaxed/simple',
				'h' => implode(':', $this->getSignedHeaders($message)),
				'd' => $this->domain,
				'bh' => $this->computeBodyHash($body),
				'b' => '',
			] as $key => $value) {
			$parts[] = $key . '=' . $value;
		}

		return $this->computeSignature($header, self::DKIM_SIGNATURE . ': ' . implode('; ', $parts));
	}


	/**
	 * @param string $rawHeader
	 * @param string $signature
	 * @return string
	 */
	protected function computeSignature(string $rawHeader, string $signature): string
	{
		$selectedHeaders = array_merge($this->signHeaders, [self::DKIM_SIGNATURE]);

		$rawHeader = preg_replace("/\r\n[ \t]+/", ' ', rtrim($rawHeader, "\r\n") . "\r\n" . $signature);

		$parts = [];
		foreach ($test = explode("\r\n", $rawHeader) as $key => $header) {
			if (strpos($header, ':') !== false) {
				[$heading, $value] = explode(':', $header, 2);

				if (($index = array_search($heading, $selectedHeaders, true)) !== false) {
					$parts[$index] =
						trim(strtolower($heading), " \t") . ':' .
						trim(preg_replace("/[ \t]{2,}/", ' ', $value), " \t");
				}
			}
		}

		ksort($parts);

		return $signature . $this->sign(implode("\r\n", $parts));
	}


	/**
	 * @param string $value
	 * @return string
	 * @throws SignException
	 */
	protected function sign(string $value): string
	{
		$privateKey = openssl_pkey_get_private($this->privateKey, $this->passPhrase);

		if ($privateKey) {
			if (openssl_sign($value, $signature, $privateKey, 'sha256WithRSAEncryption')) {
				openssl_pkey_free($privateKey);

				return base64_encode($signature);
			}
			openssl_pkey_free($privateKey);
		}
		throw new SignException('Invalid private key');
	}


	/**
	 * @param string $body
	 * @return string
	 */
	protected function computeBodyHash(string $body): string
	{
		return base64_encode(
			pack(
				'H*',
				hash('sha256', $body)
			)
		);
	}


	/**
	 * @param string $string
	 * @return string
	 */
	protected function normalizeNewLines(string $string): string
	{
		if (strlen($string) > 0) {
			return rtrim(
				str_replace("\r", "\r\n",
					str_replace(["\r\n", "\n"], "\r", $string)
				), "\r\n") . "\r\n";
		}

		return "\r\n";
	}


	/**
	 * @param Message $message
	 * @return array
	 */
	protected function getSignedHeaders(Message $message): array
	{
		return array_filter($this->signHeaders, function ($name) use ($message) {
			return $message->getHeader($name) !== null;
		});
	}
}
