<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer read timeouts.
 */

use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/SmtpMailerMock.php';


/**
 * A stream that never stops talking: every read yields another continuation line, so the response
 * never terminates. Models a server that keeps the connection busy instead of going silent.
 */
class ChattyStream
{
	/** @var resource */
	public $context;

	public static bool $slow = true;


	public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
	{
		return true;
	}


	public function stream_read(int $count): string
	{
		if (self::$slow) {
			usleep(300_000);
		}

		return "250-still talking\r\n";
	}


	public function stream_eof(): bool
	{
		return false;
	}


	public function stream_set_option(int $option, int $arg1, int $arg2): bool
	{
		return true;
	}


	public function stream_stat(): array
	{
		return [];
	}
}

stream_wrapper_register('chatty', ChattyStream::class);


class ChattyMailer extends SmtpMailerMock
{
	protected function openStream(string $address)
	{
		return fopen('chatty://server', 'r');
	}
}


test('the timeout applies even while the server keeps sending data', function () {
	ChattyStream::$slow = true;
	$mailer = new ChattyMailer(timeout: 1);

	$start = microtime(true);
	Assert::exception(fn() => $mailer->connect(), SmtpException::class, 'Connection timed out.');

	// it gave up on the deadline, not after exhausting memory
	Assert::true(microtime(true) - $start < 10);
});


test('an endless response is capped instead of eating memory', function () {
	ChattyStream::$slow = false;
	$mailer = new ChattyMailer(timeout: 0); // no deadline: only the size cap can stop this

	Assert::exception(fn() => $mailer->connect(), SmtpException::class, 'SMTP server response is too long.');
});
