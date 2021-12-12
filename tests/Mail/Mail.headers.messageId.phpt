<?php

/**
 * Test: Nette\Mail\Message custom Message-ID header.
 */

declare(strict_types=1);

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
EOD
	, TestMailer::$output);


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
EOD
	, TestMailer::$output);
