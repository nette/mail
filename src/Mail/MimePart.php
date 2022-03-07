<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;
use Nette\Utils\Strings;


/**
 * MIME message part.
 *
 * @property-deprecated   string $body
 */
class MimePart
{
	use Nette\SmartObject;

	/** encoding */
	public const
		EncodingBase64 = 'base64',
		Encoding7Bit = '7bit',
		Encoding8Bit = '8bit',
		EncodingQuotedPrintable = 'quoted-printable';

	/** @internal */
	public const EOL = "\r\n";

	public const LineLength = 76;

	/** value (RFC 2231), encoded-word (RFC 2047) */
	private const
		SequenceValue = 1,
		SequenceWord = 2;

	private array $headers = [];
	private array $parts = [];
	private string $body = '';


	/**
	 * Sets a header.
	 * @param  string|array|null  $value  value or pair email => name
	 */
	public function setHeader(string $name, string|array|null $value, bool $append = false): static
	{
		if (!$name || preg_match('#[^a-z0-9-]#i', $name)) {
			throw new Nette\InvalidArgumentException("Header name must be non-empty alphanumeric string, '$name' given.");
		}

		if ($value == null) { // intentionally ==
			if (!$append) {
				unset($this->headers[$name]);
			}
		} elseif (is_array($value)) { // email
			$tmp = &$this->headers[$name];
			if (!$append || !is_array($tmp)) {
				$tmp = [];
			}

			foreach ($value as $email => $recipient) {
				if ($recipient === null) {
					// continue
				} elseif (!Strings::checkEncoding($recipient)) {
					Nette\Utils\Validators::assert($recipient, 'unicode', "header '$name'");
				} elseif (preg_match('#[\r\n]#', $recipient)) {
					throw new Nette\InvalidArgumentException('Name must not contain line separator.');
				}

				Nette\Utils\Validators::assert($email, 'email', "header '$name'");
				$tmp[$email] = $recipient;
			}
		} else {
			$value = (string) $value;
			if (!Strings::checkEncoding($value)) {
				throw new Nette\InvalidArgumentException('Header is not valid UTF-8 string.');
			}

			$this->headers[$name] = preg_replace('#[\r\n]+#', ' ', $value);
		}

		return $this;
	}


	/**
	 * Returns a header.
	 */
	public function getHeader(string $name): mixed
	{
		return $this->headers[$name] ?? null;
	}


	/**
	 * Removes a header.
	 */
	public function clearHeader(string $name): static
	{
		unset($this->headers[$name]);
		return $this;
	}


	/**
	 * Returns an encoded header.
	 */
	public function getEncodedHeader(string $name): ?string
	{
		$offset = strlen($name) + 2; // colon + space

		if (!isset($this->headers[$name])) {
			return null;

		} elseif (is_array($this->headers[$name])) {
			$s = '';
			foreach ($this->headers[$name] as $email => $name) {
				if ($name != null) { // intentionally ==
					$s .= self::encodeSequence($name, $offset, self::SequenceWord);
					$email = " <$email>";
				}

				$s .= self::append($email . ',', $offset);
			}

			return ltrim(substr($s, 0, -1)); // last comma

		} elseif (preg_match('#^(\S+; (?:file)?name=)"(.*)"$#D', $this->headers[$name], $m)) { // Content-Disposition
			$offset += strlen($m[1]);
			return $m[1] . self::encodeSequence(stripslashes($m[2]), $offset, self::SequenceValue);

		} else {
			return ltrim(self::encodeSequence($this->headers[$name], $offset));
		}
	}


	/**
	 * Returns all headers.
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}


	/**
	 * Sets Content-Type header.
	 */
	public function setContentType(string $contentType, ?string $charset = null): static
	{
		$this->setHeader('Content-Type', $contentType . ($charset ? "; charset=$charset" : ''));
		return $this;
	}


	/**
	 * Sets Content-Transfer-Encoding header.
	 */
	public function setEncoding(string $encoding): static
	{
		$this->setHeader('Content-Transfer-Encoding', $encoding);
		return $this;
	}


	/**
	 * Returns Content-Transfer-Encoding header.
	 */
	public function getEncoding(): string
	{
		return $this->getHeader('Content-Transfer-Encoding');
	}


	/**
	 * Adds or creates new multipart.
	 */
	public function addPart(?self $part = null): self
	{
		return $this->parts[] = $part ?? new self;
	}


	/**
	 * Sets textual body.
	 */
	public function setBody(string $body): static
	{
		$this->body = $body;
		return $this;
	}


	/**
	 * Gets textual body.
	 */
	public function getBody(): string
	{
		return $this->body;
	}


	/********************* building ****************d*g**/


	/**
	 * Returns encoded message.
	 */
	public function getEncodedMessage(): string
	{
		$output = '';
		$boundary = '--------' . Nette\Utils\Random::generate();

		foreach ($this->headers as $name => $value) {
			$output .= $name . ': ' . $this->getEncodedHeader($name);
			if ($this->parts && $name === 'Content-Type') {
				$output .= ';' . self::EOL . "\tboundary=\"$boundary\"";
			}

			$output .= self::EOL;
		}

		$output .= self::EOL;

		$body = $this->body;
		if ($body !== '') {
			switch ($this->getEncoding()) {
				case self::EncodingQuotedPrintable:
					$output .= quoted_printable_encode($body);
					break;

				case self::EncodingBase64:
					$output .= rtrim(chunk_split(base64_encode($body), self::LineLength, self::EOL));
					break;

				case self::Encoding7Bit:
					$body = preg_replace('#[\x80-\xFF]+#', '', $body);
					// break omitted

				case self::Encoding8Bit:
					$body = str_replace(["\x00", "\r"], '', $body);
					$body = str_replace("\n", self::EOL, $body);
					$output .= $body;
					break;

				default:
					throw new Nette\InvalidStateException('Unknown encoding.');
			}
		}

		if ($this->parts) {
			if (!str_ends_with($output, self::EOL)) {
				$output .= self::EOL;
			}

			foreach ($this->parts as $part) {
				$output .= '--' . $boundary . self::EOL . $part->getEncodedMessage() . self::EOL;
			}

			$output .= '--' . $boundary . '--';
		}

		return $output;
	}


	/********************* QuotedPrintable helpers ****************d*g**/


	/**
	 * Converts a 8 bit header to a string.
	 */
	private static function encodeSequence(string $s, int &$offset = 0, ?int $type = null): string
	{
		if (
			(strlen($s) < self::LineLength - 3) && // 3 is tab + quotes
			strspn($s, "!\"#$%&\\'()*+,-./0123456789:;<>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^`abcdefghijklmnopqrstuvwxyz{|}~=? _\r\n\t") === strlen($s)
		) {
			if ($type && preg_match('#[^ a-zA-Z0-9!\#$%&\'*+/?^_`{|}~-]#', $s)) { // RFC 2822 atext except =
				return self::append('"' . addcslashes($s, '"\\') . '"', $offset);
			}

			return self::append($s, $offset);
		}

		$o = '';
		if ($offset >= 55) { // maximum for iconv_mime_encode
			$o = self::EOL . "\t";
			$offset = 1;
		}

		$s = iconv_mime_encode(str_repeat(' ', $old = $offset), $s, [
			'scheme' => 'B', // Q is broken
			'input-charset' => 'UTF-8',
			'output-charset' => 'UTF-8',
		]);

		$offset = strlen($s) - strrpos($s, "\n");
		$s = substr($s, $old + 2); // adds ': '
		if ($type === self::SequenceValue) {
			$s = '"' . $s . '"';
		}

		$s = str_replace("\n ", "\n\t", $s);
		return $o . $s;
	}


	private static function append(string $s, int &$offset = 0): string
	{
		if ($offset + strlen($s) > self::LineLength) {
			$offset = 1;
			$s = self::EOL . "\t" . $s;
		}

		$offset += strlen($s);
		return $s;
	}
}
