<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer read timeouts set on the stream.
 */

use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


/**
 * A stream that answers with the given lines and records the read timeouts set on it.
 */
class RecordingStream
{
	/** @var resource */
	public $context;

	/** @var list<string> */
	public static array $lines = [];

	/** @var list<int> */
	public static array $timeouts = [];


	public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
	{
		return true;
	}


	public function stream_read(int $count): string
	{
		// a line per read, as writing discards whatever PHP has buffered
		return array_shift(self::$lines) ?? '';
	}


	public function stream_write(string $data): int
	{
		return strlen($data);
	}


	public function stream_eof(): bool
	{
		return !self::$lines;
	}


	public function stream_set_option(int $option, int $arg1, int $arg2): bool
	{
		if ($option === STREAM_OPTION_READ_TIMEOUT) {
			self::$timeouts[] = $arg1;
		}

		return true;
	}


	public function stream_stat(): array
	{
		return [];
	}
}

stream_wrapper_register('recording', RecordingStream::class);


class RecordingMailer extends SmtpMailerMock
{
	protected function openStream(string $address)
	{
		return fopen('recording://server', 'r+');
	}
}


/** @return list<int> */
function recordTimeouts(int $timeout, string ...$lines): array
{
	RecordingStream::$lines = $lines;
	RecordingStream::$timeouts = [];
	(new RecordingMailer(timeout: $timeout))->connect();
	return RecordingStream::$timeouts;
}


/** @return list<int> */
function recordPersistentTimeouts(int $timeout, string ...$probe): array
{
	$transaction = ["250 sender\r\n", "250 recipient\r\n", "354 go ahead\r\n", "250 queued\r\n"];
	RecordingStream::$lines = ["220 hello\r\n", "250 OK\r\n", ...$transaction, ...$probe, ...$transaction];
	RecordingStream::$timeouts = [];

	$mail = new Message;
	$mail->setFrom('tester@example.com');
	$mail->addTo('jane@example.com');
	$mailer = new RecordingMailer(timeout: $timeout, persistent: true);
	$mailer->send($mail);
	$mailer->send($mail);
	return RecordingStream::$timeouts;
}


test('timeout 0 sets no read timeout on the stream', function () {
	Assert::same([], recordTimeouts(0, "220 hello\r\n", "250 OK\r\n"));
});


test('a positive timeout is set on the stream', function () {
	Assert::same([5], recordTimeouts(5, "220 hello\r\n", "250 OK\r\n"));
});


test('the NOOP probe waits at most 5 seconds, then the timeout is restored', function () {
	Assert::same([20, 5, 20], recordPersistentTimeouts(20, "250 noop\r\n"));
});


test('the NOOP probe waits no longer than a shorter timeout', function () {
	Assert::same([3, 3, 3], recordPersistentTimeouts(3, "250 noop\r\n"));
});


test('with timeout 0, the NOOP probe waits 5 seconds, then the PHP default applies', function () {
	ini_set('default_socket_timeout', '42');
	Assert::same([5, 42], recordPersistentTimeouts(0, "250 noop\r\n"));
});


test('a rejected probe leads to a fresh connection', function () {
	Assert::same([20, 5, 20, 20], recordPersistentTimeouts(20, "421 closing\r\n", "220 hello\r\n", "250 OK\r\n"));
});
