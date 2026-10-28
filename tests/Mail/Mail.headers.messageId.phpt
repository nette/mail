<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message Message-ID header.
 */

use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mail = new Message;

$mail->addTo('john.doe@example.com');

$mailer = new TestMailer;
$mailer->send($mail);

Assert::match(<<<'EOD'
	MIME-Version: 1.0
	X-Mailer: Nette Framework
	Date: %a%
	To: john.doe@example.com
	Message-ID: <%a%@%a%>
	Content-Type: text/plain; charset=UTF-8
	Content-Transfer-Encoding: 7bit
	EOD, TestMailer::$output);


$mail = (new Message)->setFrom('John <john@nette.org>');
Assert::match('<%a%@nette.org>', $mail->build()->getHeader('Message-ID'));

$mail->setHeader('Sender', ['bounce@mailer.example.com' => null]);
Assert::match('<%a%@mailer.example.com>', $mail->build()->getHeader('Message-ID'));

if (extension_loaded('intl')) {
	$mail = (new Message)->setFrom("john@\u{17E}lu\u{165}ou\u{10D}k\u{FD}.cz");
	Assert::match('<%a%@xn--luouk-uva4it5a4g.cz>', $mail->build()->getHeader('Message-ID'));
}

$_SERVER['HTTP_HOST'] = 'fallback.example.com';
$mail = new Message;
Closure::bind(fn() => $this->headers['From'] = ["john@\u{17E}lu\u{165}ou\u{10D}k\u{FD}.cz" => null], $mail, Nette\Mail\MimePart::class)(); // as stored without ext-intl
Assert::match('<%a%@fallback.example.com>', $mail->build()->getHeader('Message-ID'));
unset($_SERVER['HTTP_HOST']);


$mail = new Message;
$mail->addTo('john.doe@example.com');
$mail->setHeader('Message-ID', 'xxx.yyy.zzz@example.com');

$mailer->send($mail);

echo TestMailer::$output;

Assert::match(<<<'EOD'
	MIME-Version: 1.0
	X-Mailer: Nette Framework
	Date: %a%
	To: john.doe@example.com
	Message-ID: xxx.yyy.zzz@example.com
	Content-Type: text/plain; charset=UTF-8
	Content-Transfer-Encoding: 7bit
	EOD, TestMailer::$output);
