<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message internationalized domain names.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!function_exists('idn_to_ascii')) {
	Tester\Environment::skip('ext-intl not installed');
}


test('the domain of a sender and a recipient is punycoded', function () {
	$mail = new Message;
	$mail->setFrom('info@háčkovaná.cz', 'Háčkovaná');
	$mail->addTo('jan@příklad.cz');

	Assert::same(['info@xn--hkovan-ptaf40b.cz' => 'Háčkovaná'], $mail->getFrom());
	Assert::same(['jan@xn--pklad-zsa96e.cz' => null], $mail->getHeader('To'));
});


test('an ASCII address is left exactly as it is', function () {
	$mail = new Message;
	$mail->setFrom('doe@example.com');
	$mail->addTo('jane@example.com', 'Jane');

	Assert::same(['doe@example.com' => null], $mail->getFrom());
	Assert::same(['jane@example.com' => 'Jane'], $mail->getHeader('To'));
});


test('the display name keeps its diacritics: only the domain is encoded', function () {
	$mail = new Message;
	$mail->addTo('jan@příklad.cz', 'Jan Novák');

	Assert::same(['jan@xn--pklad-zsa96e.cz' => 'Jan Novák'], $mail->getHeader('To'));
});


test('Cc, Bcc and Reply-To are punycoded as well', function () {
	$mail = new Message;
	$mail->addCc('cc@příklad.cz');
	$mail->addBcc('bcc@příklad.cz');
	$mail->addReplyTo('reply@příklad.cz');

	Assert::same(['cc@xn--pklad-zsa96e.cz' => null], $mail->getHeader('Cc'));
	Assert::same(['bcc@xn--pklad-zsa96e.cz' => null], $mail->getHeader('Bcc'));
	Assert::same(['reply@xn--pklad-zsa96e.cz' => null], $mail->getHeader('Reply-To'));
});


test('the return path is punycoded, since it becomes the SMTP envelope sender', function () {
	$mail = new Message;
	$mail->setReturnPath('bounce@příklad.cz');

	Assert::same('bounce@xn--pklad-zsa96e.cz', $mail->getReturnPath());
});


test('the generated message carries an ASCII-only address', function () {
	$mail = new Message;
	$mail->setFrom('info@háčkovaná.cz');
	$mail->addTo('jan@příklad.cz');
	$mail->setBody('Hi');

	$message = $mail->generateMessage();
	Assert::contains('From: info@xn--hkovan-ptaf40b.cz', $message);
	Assert::contains('To: jan@xn--pklad-zsa96e.cz', $message);
	Assert::notContains('příklad.cz', $message);
});
