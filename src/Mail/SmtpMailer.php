<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use function in_array, is_resource;
use const STREAM_CLIENT_CONNECT, STREAM_CRYPTO_METHOD_TLS_CLIENT, STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;


/**
 * Sends emails via the SMTP server.
 */
class SmtpMailer implements Mailer
{
	public const
		EncryptionSSL = 'ssl',
		EncryptionTLS = 'tls';

	/** @var ?resource */
	private $connection;

	/** @var resource */
	private $context;
	private string $clientHost;
	private ?Signer $signer = null;


	public function __construct(
		private string $host,
		private string $username,
		#[\SensitiveParameter]
		private string $password,
		private ?int $port = null,
		private ?string $encryption = null,
		private bool $persistent = false,
		private int $timeout = 20,
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
			if (!$this->connection) {
				$this->connect();
			}

			if (
				($from = $mail->getHeader('Return-Path'))
				|| ($from = array_keys((array) $mail->getHeader('From'))[0] ?? null)
			) {
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
		} catch (SmtpException $e) {
			if ($this->connection) {
				$this->disconnect();
			}

			throw $e;
		}
	}


	/**
	 * Connects and authenticates to SMTP server.
	 */
	protected function connect(): void
	{
		$port = $this->port ?? ($this->encryption === self::EncryptionSSL ? 465 : 25);
		$this->connection = @stream_socket_client(// @ is escalated to exception
			($this->encryption === self::EncryptionSSL ? 'ssl://' : '') . $this->host . ':' . $port,
			$errno,
			$error,
			$this->timeout,
			STREAM_CLIENT_CONNECT,
			$this->context,
		);
		if (!$this->connection) {
			throw new SmtpException($error ?: error_get_last()['message'], $errno);
		}

		stream_set_timeout($this->connection, $this->timeout, 0);
		$this->read(); // greeting

		if ($this->encryption === self::EncryptionTLS) {
			$this->write("EHLO $this->clientHost", 250);
			$this->write('STARTTLS', 220);
			if (!stream_socket_enable_crypto(
				$this->connection,
				true,
				STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
			)) {
				throw new SmtpException('Unable to connect via TLS.');
			}

			$this->write("EHLO $this->clientHost");
			$ehloResponse = $this->read();
			if ((int) $ehloResponse !== 250) {
				throw new SmtpException('SMTP server did not accept EHLO with error: ' . trim($ehloResponse));
			}
		} else {
			$this->write("EHLO $this->clientHost");
			$ehloResponse = $this->read();
			if ((int) $ehloResponse !== 250) {
				$this->write("HELO $this->clientHost", 250);
			}
		}

		if ($this->username !== '') {
			$authMechanisms = [];
			if (preg_match('~^250[ -]AUTH (.*)$~im', $ehloResponse, $matches)) {
				$authMechanisms = explode(' ', trim($matches[1]));
			}

			if (in_array('PLAIN', $authMechanisms, strict: true)) {
				$credentials = $this->username . "\0" . $this->username . "\0" . $this->password;
				$this->write('AUTH PLAIN ' . base64_encode($credentials), 235, 'PLAIN credentials');
			} else {
				$this->write('AUTH LOGIN', 334);
				$this->write(base64_encode($this->username), 334, 'username');
				if ($this->password !== '') {
					$this->write(base64_encode($this->password), 235, 'password');
				}
			}
		}
	}


	/**
	 * Disconnects from SMTP server.
	 */
	protected function disconnect(): void
	{
		fclose($this->connection);
		$this->connection = null;
	}


	/**
	 * Writes data to server and checks response against expected code if some provided.
	 * @param  int|int[]  $expectedCode
	 */
	protected function write(string $line, int|array|null $expectedCode = null, ?string $message = null): void
	{
		fwrite($this->connection, $line . Message::EOL);
		if ($expectedCode) {
			$response = $this->read();
			if (!in_array((int) $response, (array) $expectedCode, strict: true)) {
				throw new SmtpException('SMTP server did not accept ' . ($message ?: $line) . ' with error: ' . trim($response));
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
			$line = @fgets($this->connection); // @ is escalated to exception
			if ($line === '' || $line === false) {
				$info = stream_get_meta_data($this->connection);
				if ($info['timed_out'] || ($endtime && time() > $endtime)) {
					throw new SmtpException('Connection timed out.');
				} elseif ($info['eof']) {
					throw new SmtpException('Connection has been closed unexpectedly.');
				}
			}

			$data .= $line;
			if (preg_match('#^.{3}(?:[ \r\n]|$)#D', $line)) {
				break;
			}
		}

		return $data;
	}
}
