<?php declare(strict_types=1);

/**
 * Test double for SmtpMailer that talks to a socket pair instead of a real server.
 *
 * The canned server responses are written into the far end of the pair up front, so the mailer
 * reads them in order as it would from a real server; everything the mailer writes is collected
 * on the far end and can be inspected afterwards.
 */

use Nette\Mail\SmtpMailer;


class SmtpMailerMock extends SmtpMailer
{
	/** @var list<string> addresses passed to openStream() */
	public array $addresses = [];

	/** @var resource|null */
	private $serverSide;
	private string $responses;


	public function __construct(string $responses = '', mixed ...$args)
	{
		$this->responses = $responses;
		parent::__construct(...($args + ['host' => 'mail.example.com', 'username' => '', 'password' => '']));
	}


	protected function openStream(string $address)
	{
		$this->addresses[] = $address;

		// a socket pair is AF_UNIX everywhere but Windows, where PHP emulates it over AF_INET
		$domain = PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX;
		$pair = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if ($pair === false) {
			throw new Exception('Unable to create a socket pair.');
		}

		[$client, $this->serverSide] = $pair;
		fwrite($this->serverSide, $this->responses);
		return $client;
	}


	public function connect(): void
	{
		parent::connect();
	}


	public function disconnect(): void
	{
		parent::disconnect();
	}


	/** Everything the mailer has written to the server so far. */
	public function getWritten(): string
	{
		if (!$this->serverSide) {
			return '';
		}

		stream_set_blocking($this->serverSide, false);
		return (string) stream_get_contents($this->serverSide);
	}


	/** @return list<string> */
	public function getWrittenLines(): array
	{
		return explode("\r\n", rtrim($this->getWritten(), "\r\n"));
	}


	/** Simulates the server closing the connection. */
	public function closeServerSide(): void
	{
		if ($this->serverSide) {
			fclose($this->serverSide);
			$this->serverSide = null;
		}
	}
}
