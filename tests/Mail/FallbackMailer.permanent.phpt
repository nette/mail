<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\FallbackMailer tells permanent failures from transient ones.
 */

use Nette\Mail\FallbackMailer;
use Nette\Mail\FallbackMailerException;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Mail\SmtpException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class CountingMailer implements Mailer
{
	public int $attempts = 0;


	public function __construct(
		private readonly ?SendException $failure,
	) {
	}


	public function send(Message $mail): void
	{
		$this->attempts++;
		if ($this->failure) {
			throw $this->failure;
		}
	}
}


test('a 5xx reply is permanent, a 4xx one is not', function () {
	Assert::true(SmtpException::fromReply('rejected', '550 no such user')->isPermanent());
	Assert::false(SmtpException::fromReply('busy', '451 try again later')->isPermanent());
	Assert::false(SmtpException::fromReply('greeting', '220 ready')->isPermanent());

	// a connection that never got a reply is worth retrying
	Assert::false((new SmtpException('Connection timed out.'))->isPermanent());

	// a plain SendException says nothing about itself, so it is retried as before
	Assert::false((new SendException('boom'))->isPermanent());
});


test('a permanently failing mailer is not retried, but the fallback still runs', function () {
	$rejecting = new CountingMailer(SmtpException::fromReply('rejected', '550 mailbox unavailable'));
	$working = new CountingMailer(null);

	(new FallbackMailer([$rejecting, $working], retryCount: 3, retryWaitTime: 10))->send(new Message);

	Assert::same(1, $rejecting->attempts); // asked once, and it said no for good
	Assert::same(1, $working->attempts);
});


test('a transient failure is still retried', function () {
	$busy = new CountingMailer(SmtpException::fromReply('busy', '421 service not available'));

	$mailer = new FallbackMailer([$busy], retryCount: 3, retryWaitTime: 10);
	Assert::exception(fn() => $mailer->send(new Message), FallbackMailerException::class);

	Assert::same(3, $busy->attempts);
});


test('when every mailer refuses permanently, it gives up at once', function () {
	$a = new CountingMailer(SmtpException::fromReply('rejected', '550 no such user'));
	$b = new CountingMailer(new SmtpException('Bad credentials.', permanent: true));

	$mailer = new FallbackMailer([$a, $b], retryCount: 5, retryWaitTime: 10_000); // a wait we must not sit through

	$start = microtime(true);
	$e = Assert::exception(fn() => $mailer->send(new Message), FallbackMailerException::class);

	Assert::same(1, $a->attempts);
	Assert::same(1, $b->attempts);
	Assert::count(2, $e->failures); // both refusals are reported
	Assert::true(microtime(true) - $start < 1); // no retry round, so no wait
});


test('a mailer that refuses permanently drops out while the others keep being retried', function () {
	$rejecting = new CountingMailer(SmtpException::fromReply('rejected', '550 no such user'));
	$busy = new CountingMailer(SmtpException::fromReply('busy', '451 try again later'));

	$mailer = new FallbackMailer([$rejecting, $busy], retryCount: 3, retryWaitTime: 10);
	Assert::exception(fn() => $mailer->send(new Message), FallbackMailerException::class);

	Assert::same(1, $rejecting->attempts);
	Assert::same(3, $busy->attempts);
});
