<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use function in_array, is_resource, is_string, strlen, substr;


/**
 * Sends emails via the SMTP server.
 */
class SmtpMailer implements Mailer
{
	public const
		EncryptionSSL = 'ssl',
		EncryptionTLS = 'tls';

	private const MaxResponseLength = 1_000_000;

	private const WriteChunkLength = 8192;

	/** @var ?resource */
	private $connection;

	/** @var resource */
	private $context;
	private string $clientHost;
	private ?Signer $signer = null;

	/** @var ?\Closure(): string */
	private ?\Closure $accessToken = null;


	/** @param array<string, array<string, mixed>>|null  $streamOptions */
	public function __construct(
		private readonly string $host,
		private readonly string $username,
		#[\SensitiveParameter]
		private readonly string $password,
		private readonly ?int $port = null,
		private readonly ?string $encryption = null,
		private readonly bool $persistent = false,
		private readonly int $timeout = 20,
		?string $clientHost = null,
		?array $streamOptions = null,
	) {
		$this->context = $streamOptions === null
			? stream_context_get_default()
			: stream_context_create($streamOptions);

		if ($clientHost === null) {
			$this->clientHost = isset($_SERVER['HTTP_HOST']) && preg_match('#^[\w.-]+$#D', $_SERVER['HTTP_HOST'])
				? $_SERVER['HTTP_HOST']
				: 'localhost';
		} else {
			$this->clientHost = $clientHost;
		}
	}


	public function setSigner(Signer $signer): static
	{
		$this->signer = $signer;
		return $this;
	}


	/**
	 * Authenticates with an OAuth 2.0 access token (XOAUTH2), as required by Gmail and Microsoft 365.
	 * A callback is resolved on each connection, so an expiring token can be renewed.
	 * @param  string|\Closure(): string  $token
	 */
	public function setAccessToken(string|\Closure $token): static
	{
		$this->accessToken = is_string($token) ? fn() => $token : $token;
		return $this;
	}


	/**
	 * Sends email.
	 * @throws SmtpException
	 */
	public function send(Message $mail): void
	{
		$tmp = clone $mail;
		$tmp->setHeader('Bcc', null);
		if (!$tmp->getHeader('To') && !$tmp->getHeader('Cc')) {
			// missing recipient headers make some mailers (e.g., sendmail) nervous -> set 'To' like many MTAs do
			$tmp->setHeader('To', 'undisclosed-recipients: ;');
		}

		$data = $this->signer
			? $this->signer->generateSignedMessage($tmp)
			: $tmp->generateMessage();

		try {
			if ($this->connection && !$this->isAlive()) {
				$this->disconnect(); // the session went stale between sends, start over
			}

			if (!$this->connection) {
				$this->connect();
			}

			$from = $mail->getHeader('Return-Path');
			if (!is_string($from)) {
				$from = array_keys((array) $mail->getHeader('From'))[0] ?? null;
			}

			if (is_string($from)) {
				$this->write("MAIL FROM:<$from>", 250);
			}

			foreach (array_merge(
				(array) $mail->getHeader('To'),
				(array) $mail->getHeader('Cc'),
				(array) $mail->getHeader('Bcc'),
			) as $email => $name) {
				$this->write("RCPT TO:<$email>", [250, 251]);
			}

			$this->write('DATA', 354);
			$data = preg_replace('#^\.#m', '..', $data);
			$this->write($data);
			$this->write('.', 250);

			if (!$this->persistent) {
				$this->write('QUIT', 221);
				$this->disconnect();
			}
		} catch (\Throwable $e) {
			// not just SmtpException: an access-token callback may throw anything, and the connection
			// is now in an unknown state
			if ($this->connection) {
				$this->disconnect();
			}

			throw $e;
		}
	}


	/**
	 * Tells whether a connection kept open between sends is still usable; servers hang up on idle sessions.
	 */
	private function isAlive(): bool
	{
		if (!is_resource($this->connection) || feof($this->connection)) {
			return false;
		}

		try {
			$this->write('NOOP', 250);
			return true;
		} catch (SmtpException) {
			return false;
		}
	}


	/**
	 * Connects and authenticates to SMTP server.
	 */
	protected function connect(): void
	{
		$connection = $this->openStream($this->getAddress());
		$this->connection = $connection;

		stream_set_timeout($connection, $this->timeout);
		$this->read(); // greeting

		if ($this->encryption === self::EncryptionTLS) {
			$this->write("EHLO $this->clientHost", 250);
			$this->write('STARTTLS', 220);
			$this->upgradeToTls($connection);
			$this->write("EHLO $this->clientHost");
			$ehloResponse = $this->read();
			if ((int) $ehloResponse !== 250) {
				throw SmtpException::fromReply('SMTP server did not accept EHLO with error: ' . trim($ehloResponse), $ehloResponse);
			}
		} else {
			$this->write("EHLO $this->clientHost");
			$ehloResponse = $this->read();
			if ((int) $ehloResponse !== 250) {
				$this->write("HELO $this->clientHost", 250);
			}
		}

		if ($this->username !== '' || $this->accessToken) {
			$this->authenticate($ehloResponse);
		}
	}


	/**
	 * Authenticates with a mechanism the server advertises in its EHLO response.
	 * @throws SmtpException
	 */
	private function authenticate(string $ehloResponse): void
	{
		// 'AUTH PLAIN LOGIN' is the standard form, 'AUTH=PLAIN LOGIN' the legacy one some servers still emit
		$mechanisms = preg_match('~^250[ -]AUTH[ =](.*)$~im', $ehloResponse, $m)
			? explode(' ', trim($m[1]))
			: [];

		// these are configuration mismatches: the server will not grow the mechanism on a retry
		if (!$mechanisms) {
			throw new SmtpException('SMTP server does not support authentication.', permanent: true);

		} elseif ($this->accessToken) {
			if ($this->username === '') {
				throw new SmtpException('XOAUTH2 needs a username: the token alone does not say who is signing in.', permanent: true);

			} elseif (!in_array('XOAUTH2', $mechanisms, strict: true)) {
				throw new SmtpException('SMTP server does not support XOAUTH2 authentication.', permanent: true);
			}

			$this->authenticateOAuth(($this->accessToken)());

		} elseif (in_array('PLAIN', $mechanisms, strict: true)) {
			$credentials = $this->username . "\0" . $this->username . "\0" . $this->password;
			$this->write('AUTH PLAIN ' . base64_encode($credentials), 235, 'PLAIN credentials');

		} elseif (in_array('LOGIN', $mechanisms, strict: true)) {
			$this->write('AUTH LOGIN', 334);
			$this->write(base64_encode($this->username), 334, 'username');
			// the password line is sent even when empty: the server is waiting for it and would otherwise stall
			$this->write(base64_encode($this->password), 235, 'password');

		} else {
			throw new SmtpException(
				'SMTP server does not offer a supported authentication mechanism, only: ' . implode(', ', $mechanisms) . '.',
				permanent: true,
			);
		}
	}


	/**
	 * Performs the XOAUTH2 exchange (https://developers.google.com/gmail/imap/xoauth2-protocol).
	 * @throws SmtpException
	 */
	private function authenticateOAuth(#[\SensitiveParameter] string $token): void
	{
		$credentials = "user=$this->username\1auth=Bearer $token\1\1";
		$this->write('AUTH XOAUTH2 ' . base64_encode($credentials));
		$response = $this->read();

		if ((int) $response === 334) {
			// the rejection reason came in the challenge; the server expects an empty line before the final failure
			$this->write('');
			$response = $this->read();
		}

		if ((int) $response !== 235) {
			throw SmtpException::fromReply('SMTP server did not accept XOAUTH2 credentials with error: ' . trim($response), $response);
		}
	}


	/**
	 * Upgrades the connection to TLS 1.2 or 1.3; the earlier versions are deprecated (RFC 8996).
	 * @param  resource  $connection
	 * @throws SmtpException
	 */
	private function upgradeToTls($connection): void
	{
		$info = '';
		$res = Nette\Utils\Callback::invokeSafe(
			'stream_socket_enable_crypto',
			[$connection, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT],
			function (string $message) use (&$info): void {
				$info = ": $message";
			},
		);

		if ($res !== true) {
			throw new SmtpException("Unable to connect via TLS$info.");
		}
	}


	/**
	 * Returns the address to connect to. Without an explicit port, uses the default for the encryption:
	 * 465 for implicit SSL, 587 (the submission port) for STARTTLS, 25 otherwise.
	 */
	private function getAddress(): string
	{
		$port = $this->port ?? match ($this->encryption) {
			self::EncryptionSSL => 465,
			self::EncryptionTLS => 587,
			default => 25,
		};

		return ($this->encryption === self::EncryptionSSL ? 'ssl://' : '') . $this->host . ':' . $port;
	}


	/**
	 * Opens the network stream to the SMTP server.
	 * @return resource
	 * @throws SmtpException
	 */
	protected function openStream(string $address)
	{
		$connection = @stream_socket_client(// @ is escalated to exception
			$address,
			$errno,
			$error,
			$this->timeout,
			STREAM_CLIENT_CONNECT,
			$this->context,
		);
		if (!$connection) {
			throw new SmtpException($error ?: error_get_last()['message'] ?? 'Unknown error', (int) $errno);
		}

		return $connection;
	}


	protected function disconnect(): void
	{
		if ($this->connection) {
			fclose($this->connection);
			$this->connection = null;
		}
	}


	/**
	 * Writes data to server and checks response against expected code if some provided.
	 * @param int|list<int>|null  $expectedCode
	 */
	protected function write(string $line, int|array|null $expectedCode = null, ?string $message = null): void
	{
		$connection = $this->connection ?? throw new SmtpException('Not connected to SMTP server.');
		$data = $line . Message::EOL;
		$length = strlen($data);
		error_clear_last(); // so that a stale error is not reported as ours

		// fwrite() may write only part of the data; a fixed window avoids re-copying the whole remainder
		for ($written = 0; $written < $length; $written += $res) {
			$res = @fwrite($connection, substr($data, $written, self::WriteChunkLength)); // @ is escalated to exception
			if (!$res) {
				$info = stream_get_meta_data($connection);
				throw new SmtpException($info['timed_out']
					? 'Connection timed out.'
					: 'Unable to write to the SMTP server: ' . (error_get_last()['message'] ?? 'connection lost'));
			}
		}

		if ($expectedCode) {
			$response = $this->read();
			if (!in_array((int) $response, (array) $expectedCode, strict: true)) {
				throw SmtpException::fromReply(
					'SMTP server did not accept ' . ($message ?: $line) . ' with error: ' . trim($response),
					$response,
				);
			}
		}
	}


	/**
	 * Reads response from server.
	 */
	protected function read(): string
	{
		$data = '';
		$endtime = $this->timeout > 0 ? time() + $this->timeout : 0;

		while (is_resource($this->connection) && !feof($this->connection)) {
			// the deadline holds even while the server keeps sending
			if ($endtime && time() > $endtime) {
				throw new SmtpException('Connection timed out.');
			}

			// bounded, or a newline-less response would exhaust memory inside the call
			$line = @fgets($this->connection, self::MaxResponseLength); // @ is escalated to exception
			if ($line === '' || $line === false) {
				$info = stream_get_meta_data($this->connection);
				if ($info['timed_out']) {
					throw new SmtpException('Connection timed out.');
				} elseif ($info['eof']) {
					throw new SmtpException('Connection has been closed unexpectedly.');
				}

				continue;
			}

			$data .= $line;
			if (strlen($data) > self::MaxResponseLength) {
				throw new SmtpException('SMTP server response is too long.');
			}

			if (preg_match('#^.{3}(?:[ \r\n]|$)#D', $line)) {
				break;
			}
		}

		return $data;
	}
}
