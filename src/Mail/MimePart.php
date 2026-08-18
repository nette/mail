<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use function addcslashes, array_keys, base64_encode, chunk_split, iconv_mime_encode, is_array, is_string, ltrim, preg_match, preg_replace, quoted_printable_encode, rtrim, sprintf, str_ends_with, str_repeat, str_replace, strcasecmp, stripslashes, strlen, strpbrk, strrpos, strspn, substr;


/**
 * MIME message part.
 *
 * @property-deprecated   string $body
 */
class MimePart
{
	use Nette\SmartObject;

	/** Content-Transfer-Encoding values */
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

	/** @var array<string, string|array<string, ?string>> */
	private array $headers = [];

	/** @var list<MimePart> */
	private array $parts = [];
	private string $body = '';


	/**
	 * Converts an internationalized domain in the address to its ASCII (punycode) form; a UTF-8 domain
	 * on the wire requires SMTPUTF8 (RFC 6531). Needs ext-intl. The local part is never touched.
	 */
	protected static function toAsciiEmail(string $email): string
	{
		$pos = strrpos($email, '@');
		if (
			$pos === false
			|| !preg_match('#[\x80-\xFF]#', substr($email, $pos + 1))
			|| !function_exists('idn_to_ascii')
		) {
			return $email;
		}

		$domain = idn_to_ascii(substr($email, $pos + 1), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
		return $domain === false
			? $email
			: substr($email, 0, $pos + 1) . $domain;
	}


	/**
	 * Returns the spelling under which the header is already stored; names are case-insensitive (RFC 5322).
	 */
	private function resolveName(string $name): string
	{
		return Arrays::first(array_keys($this->headers), fn($key) => strcasecmp($key, $name) === 0)
			?? $name;
	}


	/**
	 * Sets a header.
	 * @param  string|array<string, ?string>|null  $value  value or pair email => name
	 */
	public function setHeader(string $name, string|array|null $value, bool $append = false): static
	{
		if (!$name || preg_match('#[^a-z0-9-]#i', $name)) {
			throw new Nette\InvalidArgumentException("Header name must be non-empty alphanumeric string, '$name' given.");
		}

		$name = $this->resolveName($name);

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
				$tmp[self::toAsciiEmail($email)] = $recipient;
			}
		} else {
			if (!Strings::checkEncoding($value)) {
				throw new Nette\InvalidArgumentException('Header is not valid UTF-8 string.');
			}

			$this->headers[$name] = preg_replace('#[\r\n]+#', ' ', $value);
		}

		return $this;
	}


	/**
	 * Returns the header value, or null if not set.
	 * @return string|array<string, ?string>|null
	 */
	public function getHeader(string $name): string|array|null
	{
		return $this->headers[$this->resolveName($name)] ?? null;
	}


	public function clearHeader(string $name): static
	{
		unset($this->headers[$this->resolveName($name)]);
		return $this;
	}


	/**
	 * Returns an encoded header.
	 */
	public function getEncodedHeader(string $name): ?string
	{
		$name = $this->resolveName($name);
		$value = $this->headers[$name] ?? null;
		$offset = strlen($name) + 2; // colon + space

		if ($value === null) {
			return null;

		} elseif (is_array($value)) {
			$s = '';
			foreach ($value as $email => $recipient) {
				if ($recipient != null) { // intentionally ==
					$s .= self::encodeSequence($recipient, $offset, self::SequenceWord);
					$email = " <$email>";
				}

				$s .= self::append($email . ',', $offset);
			}

			return ltrim(substr($s, 0, -1)); // last comma

		} elseif (preg_match('#^(\S+; (?:file)?name=)"(.*)"$#D', $value, $m)) { // Content-Disposition
			$offset += strlen($m[1]);
			return $m[1] . self::encodeSequence(stripslashes($m[2]), $offset, self::SequenceValue);

		} else {
			return ltrim(self::encodeSequence($value, $offset));
		}
	}


	/**
	 * Returns all headers.
	 * @return array<string, string|array<string, ?string>>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}


	public function setContentType(string $contentType, ?string $charset = null): static
	{
		$this->setHeader('Content-Type', $contentType . ($charset ? "; charset=$charset" : ''));
		return $this;
	}


	public function setEncoding(string $encoding): static
	{
		$this->setHeader('Content-Transfer-Encoding', $encoding);
		return $this;
	}


	public function getEncoding(): string
	{
		$encoding = $this->getHeader('Content-Transfer-Encoding');
		return is_string($encoding) ? $encoding : '';
	}


	/**
	 * Adds or creates new multipart.
	 */
	public function addPart(?self $part = null): self
	{
		return $this->parts[] = $part ?? new self;
	}


	public function setBody(string $body): static
	{
		$this->body = $body;
		return $this;
	}


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
			// the name is stored as it was first written, which need not be the canonical spelling
			if ($this->parts && strcasecmp($name, 'Content-Type') === 0) {
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
	 * MIME-encodes a string for use in a header, handling line length and folding.
	 */
	private static function encodeSequence(string $s, int &$offset = 0, ?int $type = null): string
	{
		if (
			(strlen($s) < self::LineLength - 3) && // 3 is tab + quotes
			strspn($s, "!\"#$%&\\'()*+,-./0123456789:;<>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^`abcdefghijklmnopqrstuvwxyz{|}~=? _\r\n\t") === strlen($s)
		) {
			if ($type !== null && preg_match('#[^ a-zA-Z0-9!\#$%&\'*+/?^_`{|}~-]#', $s) === 1) { // RFC 2822 atext except =
				$s = sprintf('"%s"', addcslashes($s, '"\\'));
			}

			return self::append($s, $offset);
		}

		$o = '';
		if ($offset >= 55) { // maximum for iconv_mime_encode
			$o = self::EOL . "\t";
			$offset = 1;
		}

		if ( // could split the address list when decoded before parsing
			$type === self::SequenceWord
			&& strpbrk($s, ',;:<>@"\\') !== false
		) {
			$s = sprintf('"%s"', addcslashes($s, '"\\'));
		}

		$s = iconv_mime_encode(str_repeat(' ', $old = $offset), $s, [
			'scheme' => 'B', // Q is broken
			'input-charset' => 'UTF-8',
			'output-charset' => 'UTF-8',
		]) ?: '';

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
