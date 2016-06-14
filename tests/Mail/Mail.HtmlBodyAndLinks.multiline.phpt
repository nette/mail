<?php

/**
 * Test: Nette\Mail\Message - HTML body.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/Mail.php';


$mail = new Message();

$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');

$mail->setHTMLBody('<b><span>Příliš </span> <a href="http://green.example.com">žluťoučký</a><br>
	<a
		href=\'http://horse.example.com\'
		style="abc"
	>
		žlutý
		kůň
	</a>
</b>');

$mailer = new TestMailer();
$mailer->send($mail);

Assert::match(<<<EOD
MIME-Version: 1.0
X-Mailer: Nette Framework
Date: %a%
From: John Doe <doe@example.com>
To: Lady Jane <jane@example.com>
Subject: Hello Jane!
Message-ID: <%S%@%S%>
Content-Type: multipart/alternative;
	boundary="--------%S%"

----------%S%
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit

Příliš žluťoučký <http://green.example.com>
 žlutý kůň <http://horse.example.com>
----------%S%
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 8bit

<b><span>Příliš </span> <a href="http://green.example.com">žluťoučký</a><br>
	<a
		href='http://horse.example.com'
		style="abc"
	>
		žlutý
		kůň
	</a>
</b>
----------%S%--
EOD
, TestMailer::$output);
