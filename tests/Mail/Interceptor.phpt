<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Interceptor
 */

use Nette\Mail\Interceptor;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class CapturingMailer implements Mailer
{
	public ?Message $sent = null;
	public ?SendException $throwOnSend = null;


	public function send(Message $mail): void
	{
		if ($this->throwOnSend !== null) {
			throw $this->throwOnSend;
		}

		$this->sent = $mail;
	}
}


// ---- passthrough (no redirect) ----

test('passthrough without redirect', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner);

	$mail = new Message;
	$mail->addTo('alice@example.com');
	$mailer->send($mail);

	Assert::same(['alice@example.com' => null], $inner->sent->getHeader('To'));
	Assert::null($inner->sent->getHeader('X-Original-To'));
});


// ---- redirect ----

test('redirects To/Cc/Bcc and preserves originals in X-Original-* headers', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$mail = new Message;
	$mail->setFrom('sender@example.com')
		->addTo('alice@example.com', 'Alice')
		->addCc('bob@example.com')
		->addBcc('charlie@example.com');

	$mailer->send($mail);

	Assert::same(['dev@example.com' => null], $inner->sent->getHeader('To'));
	Assert::null($inner->sent->getHeader('Cc'));
	Assert::null($inner->sent->getHeader('Bcc'));
	Assert::same('Alice <alice@example.com>', $inner->sent->getHeader('X-Original-To'));
	Assert::same('bob@example.com', $inner->sent->getHeader('X-Original-Cc'));
	Assert::same('charlie@example.com', $inner->sent->getHeader('X-Original-Bcc'));
});


test('does not mutate the original message', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$mail = new Message;
	$mail->addTo('alice@example.com', 'Alice');
	$mail->setSubject('Hello');

	$mailer->send($mail);

	Assert::same(['alice@example.com' => 'Alice'], $mail->getHeader('To'));
	Assert::same('Hello', $mail->getSubject());
	Assert::null($mail->getHeader('X-Original-To'));
});


test('subjectPrefix prepends to subject', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com', '[debug]');

	$mail = new Message;
	$mail->setSubject('Hello');
	$mailer->send($mail);

	Assert::same('[debug] Hello', $inner->sent->getSubject());
});


test('subjectPrefix on message without subject has no trailing space', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com', '[debug]');

	$mailer->send(new Message);

	Assert::same('[debug]', $inner->sent->getSubject());
});


test('strips Disposition-Notification-To and X-Confirm-Reading-To to X-Original-*', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$mail = new Message;
	$mail->setHeader('Disposition-Notification-To', 'sender@example.com');
	$mail->setHeader('X-Confirm-Reading-To', 'sender@example.com');
	$mailer->send($mail);

	Assert::null($inner->sent->getHeader('Disposition-Notification-To'));
	Assert::null($inner->sent->getHeader('X-Confirm-Reading-To'));
	Assert::same('sender@example.com', $inner->sent->getHeader('X-Original-Disposition-Notification-To'));
	Assert::same('sender@example.com', $inner->sent->getHeader('X-Original-X-Confirm-Reading-To'));
});


testException(
	'rejects "Name <email>" format - only pure emails allowed',
	fn() => new Interceptor(new CapturingMailer, 'Dev Team <dev@example.com>'),
	Nette\Utils\AssertionException::class,
);


testException(
	'rejects empty string',
	fn() => new Interceptor(new CapturingMailer, ''),
	Nette\Utils\AssertionException::class,
);


testException(
	'rejects malformed email',
	fn() => new Interceptor(new CapturingMailer, 'not-an-email'),
	Nette\Utils\AssertionException::class,
);


test('handles message without any recipients', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$mailer->send(new Message);

	Assert::same(['dev@example.com' => null], $inner->sent->getHeader('To'));
	Assert::null($inner->sent->getHeader('X-Original-To'));
});


test('formats multiple original recipients comma-separated', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$mail = new Message;
	$mail->addTo('alice@example.com', 'Alice')
		->addTo('bob@example.com');
	$mailer->send($mail);

	Assert::same('Alice <alice@example.com>, bob@example.com', $inner->sent->getHeader('X-Original-To'));
});


// ---- $onSent event ----

test('onSent fires after successful send with null error', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner);

	$called = [];
	$mailer->onSent[] = function (Interceptor $sender, Message $mail, ?Throwable $error) use (&$called): void {
		$called[] = [$sender, $mail, $error];
	};

	$mail = new Message;
	$mail->setSubject('Hello');
	$mailer->send($mail);

	Assert::count(1, $called);
	Assert::same($mailer, $called[0][0]);
	Assert::same('Hello', $called[0][1]->getSubject());
	Assert::null($called[0][2]);
});


test('onSent fires with exception when inner mailer throws', function () {
	$inner = new CapturingMailer;
	$inner->throwOnSend = new SendException('SMTP refused');
	$mailer = new Interceptor($inner);

	$called = [];
	$mailer->onSent[] = function (Interceptor $sender, Message $mail, ?Throwable $error) use (&$called): void {
		$called[] = [$sender, $mail, $error];
	};

	Assert::exception(
		fn() => $mailer->send(new Message),
		SendException::class,
		'SMTP refused',
	);
	Assert::count(1, $called);
	Assert::type(SendException::class, $called[0][2]);
});


test('onSent supports multiple subscribers', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner);

	$calls = 0;
	$mailer->onSent[] = function () use (&$calls): void { $calls++; };
	$mailer->onSent[] = function () use (&$calls): void { $calls++; };

	$mailer->send(new Message);

	Assert::same(2, $calls);
});


test('onSent sees the original (pre-redirect) message', function () {
	$inner = new CapturingMailer;
	$mailer = new Interceptor($inner, 'dev@example.com');

	$receivedTo = null;
	$mailer->onSent[] = function (Interceptor $sender, Message $mail) use (&$receivedTo): void {
		$receivedTo = $mail->getHeader('To');
	};

	$mail = new Message;
	$mail->addTo('alice@example.com');
	$mailer->send($mail);

	Assert::same(['alice@example.com' => null], $receivedTo);
});
