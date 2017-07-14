<?php

/**
 * Test: Nette\Mail\Message hostname.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mailer = new TestMailer();

$mail = new Message('nette.org');
$mailer->send($mail);

Assert::match('MIME-Version: 1.0
X-Mailer: Nette Framework
Date: %a%
Message-ID: <%S%@nette.org>
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit
', TestMailer::$output);


$hostname =  php_uname('n');

$mail = new Message();
$mailer->send($mail);

Assert::match("MIME-Version: 1.0
X-Mailer: Nette Framework
Date: %a%
Message-ID: <%S%@$hostname>
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit
", TestMailer::$output);

$_SERVER['HTTP_HOST'] = 'http.host';

$mail = new Message();
$mailer->send($mail);

Assert::match('MIME-Version: 1.0
X-Mailer: Nette Framework
Date: %a%
Message-ID: <%S%@http.host>
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit
', TestMailer::$output);
