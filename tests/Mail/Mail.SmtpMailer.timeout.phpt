<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer read timeouts set on the stream.
 */

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


test('timeout 0 sets no read timeout on the stream', function () {
	Assert::same([], recordTimeouts(0, "220 hello\r\n", "250 OK\r\n"));
});


test('a positive timeout is set on the stream', function () {
	Assert::same([5], recordTimeouts(5, "220 hello\r\n", "250 OK\r\n"));
});
