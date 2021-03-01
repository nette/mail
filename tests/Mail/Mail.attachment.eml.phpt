<?php

/**
 * Test: Nette\Mail\Message - attachments.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mailer = new TestMailer;

$mail = new Message;
$mail->addAttachment(__DIR__ . '/fixtures/example.eml', null, 'MESSAGE/RFC822');
$mailer->send($mail);

Assert::match(<<<'EOD'
	MIME-Version: 1.0
	X-Mailer: Nette Framework
	Date: %a%
	Message-ID: <%S%@%S%>
	Content-Type: multipart/mixed;
		boundary="--------%S%"

	----------%S%
	Content-Type: text/plain; charset=UTF-8
	Content-Transfer-Encoding: 7bit


	----------%S%
	Content-Type: application/octet-stream
	Content-Transfer-Encoding: base64
	Content-Disposition: attachment; filename="example.eml"

	Um%A%=
	----------%S%--
	EOD, TestMailer::$output);
