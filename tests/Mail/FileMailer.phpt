<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\FileMailer
 */

use Nette\Mail\FileMailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$tempDir = sys_get_temp_dir() . '/FileMailer.' . getmypid();
Tester\Helpers::purge($tempDir);


function createMail(): Message
{
	$mail = new Message;
	$mail->setFrom('doe@example.com');
	$mail->addTo('jane@example.com');
	$mail->setSubject('Hello Jane!');
	$mail->setBody('Příliš žluťoučký kůň');
	return $mail;
}


test('the message is written as a complete .eml file', function () use ($tempDir) {
	$dir = "$tempDir/one";
	$mailer = new FileMailer($dir);

	$mailer->send(createMail());

	$files = glob("$dir/*.eml");
	Assert::count(1, $files);

	$content = file_get_contents($files[0]);
	Assert::contains('From: doe@example.com', $content);
	Assert::contains('To: jane@example.com', $content);
	Assert::contains('Subject: Hello Jane!', $content);
	Assert::contains('Příliš žluťoučký kůň', $content);
});


test('the directory is created on demand', function () use ($tempDir) {
	$dir = "$tempDir/deep/nested/mails";
	Assert::false(is_dir($dir));

	(new FileMailer($dir))->send(createMail());

	Assert::true(is_dir($dir));
});


test('every message gets its own file', function () use ($tempDir) {
	$dir = "$tempDir/many";
	$mailer = new FileMailer($dir);

	$mailer->send(createMail());
	$mailer->send(createMail());
	$mailer->send(createMail());

	Assert::count(3, glob("$dir/*.eml"));
});


test('the path of the last message is exposed', function () use ($tempDir) {
	$dir = "$tempDir/last";
	$mailer = new FileMailer($dir);

	Assert::null($mailer->getLastPath());

	$mailer->send(createMail());

	Assert::same(realpath(glob("$dir/*.eml")[0]), realpath($mailer->getLastPath()));
});


test('the signer is applied when set', function () use ($tempDir) {
	if (!extension_loaded('openssl')) {
		return;
	}

	$dir = "$tempDir/signed";
	$mailer = new FileMailer($dir);
	$mailer->setSigner(new Nette\Mail\DkimSigner('nette.org', 'selector', file_get_contents(__DIR__ . '/fixtures/private.key')));

	$mailer->send(createMail());

	Assert::contains('DKIM-Signature: v=1;', file_get_contents(glob("$dir/*.eml")[0]));
});


test('an unwritable target is reported as a SendException', function () use ($tempDir) {
	// a file sits where the directory would have to be, so creating it cannot succeed
	$blocker = "$tempDir/blocker";
	file_put_contents($blocker, 'not a directory');

	$mailer = new FileMailer("$blocker/mails");

	Assert::exception(fn() => $mailer->send(createMail()), SendException::class, "Unable to write email to '%a%'.");
});
