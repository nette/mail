<?php

/**
 * Test: Nette\Mail\FallbackMailer
 */

declare(strict_types=1);

use Nette\Mail\FallbackMailer;
use Nette\Mail\FallbackMailerException;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


class FailingMailer implements IMailer
{
	private $failedTimes;


	public function __construct($failedTimes)
	{
		$this->failedTimes = $failedTimes;
	}


	public function send(Message $mail): void
	{
		static $count = 0;
		if ($this->failedTimes--) {
			throw new SendException('Failure #' . (++$count));
		}
	}
}


test('', function () {
	$subMailerA = new FailingMailer(3);
	$subMailerB = new FailingMailer(3);

	$mailer = new FallbackMailer([$subMailerA, $subMailerB], 3, 10);
	$mailer->onFailure[] = function (FallbackMailer $sender, SendException $e, IMailer $mailer, Message $mail) use (&$onFailureCalls) {
		$onFailureCalls[] = $mailer;
	};

	$e = Assert::exception(
		fn() => $mailer->send(new Message),
		FallbackMailerException::class,
		'All mailers failed to send the message.',
	);
	Assert::same([$subMailerA, $subMailerB, $subMailerA, $subMailerB, $subMailerA, $subMailerB], $onFailureCalls);
	Assert::count(6, $e->failures);
	Assert::same('Failure #1', $e->failures[0]->getMessage());
});


test('', function () {
	$subMailerA = new FailingMailer(3);
	$subMailerB = new FailingMailer(2);

	$mailer = new FallbackMailer([$subMailerA, $subMailerB], 3, 10);
	$mailer->onFailure[] = function (FallbackMailer $sender, SendException $e, IMailer $mailer, Message $mail) use (&$onFailureCalls) {
		$onFailureCalls[] = $mailer;
	};

	$mailer->send(new Message);
	Assert::same([$subMailerA, $subMailerB, $subMailerA, $subMailerB, $subMailerA], $onFailureCalls);
});


test('', function () {
	$subMailerA = new FailingMailer(0);
	$subMailerB = new FailingMailer(2);

	$mailer = new FallbackMailer([$subMailerA, $subMailerB], 3, 10);
	$mailer->onFailure[] = function (FallbackMailer $sender, SendException $e, IMailer $mailer, Message $mail) use (&$onFailureCalls) {
		$onFailureCalls[] = $mailer;
	};

	$mailer->send(new Message);
	Assert::null($onFailureCalls);
});
