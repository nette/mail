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
$mail->addAttachment(__DIR__ . '/fixtures/example.zip', null, 'application/zip');
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
Content-Type: application/zip
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="example.zip"

UEsDBBQAAAAIACeIMjsmkSpnQAAAAEEAAAALAAAAdmVyc2lvbi50eHTzSy0pSVVwK0rMTS3PL8pW
MNCz1DNU0ChKLcsszszPU0hJNjMwTzNQKErNSU0sTk1RAIoZGRhY6gKRoYUmLxcAUEsBAhQAFAAA
AAgAJ4gyOyaRKmdAAAAAQQAAAAsAAAAAAAAAAAAgAAAAAAAAAHZlcnNpb24udHh0UEsFBgAAAAAB
AAEAOQAAAGkAAAAAAA==
----------%S%--
EOD
	, TestMailer::$output);


$mail = new Message;
$mail->addAttachment(__DIR__ . '/fixtures/example.zip', null, 'application/zip')
	->setEncoding(Message::ENCODING_QUOTED_PRINTABLE);
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
Content-Type: application/zip
Content-Transfer-Encoding: quoted-printable
Content-Disposition: attachment; filename="example.zip"

PK=03=04=14=00=00=00=08=00'=882;&=91*g@=00=00=00A=00=00=00=0B=00=00=00versi=%A%00
----------%S%--
EOD
	, TestMailer::$output);


$mail = new Message;
$mail->addAttachment('žluťoučký.zip', file_get_contents(__DIR__ . '/fixtures/small.bin'), 'application/octet-stream');
$mail->addAttachment('veryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryveryverylongemail.pdf', file_get_contents(__DIR__ . '/fixtures/small.bin'), 'application/octet-stream');
$mail->addAttachment('ž', file_get_contents(__DIR__ . '/fixtures/small.bin'), 'application/octet-stream');
$mail->addAttachment('abc', file_get_contents(__DIR__ . '/fixtures/small.bin'), 'application/octet-stream');
$mail->addAttachment('"\\', file_get_contents(__DIR__ . '/fixtures/small.bin'), 'application/octet-stream');
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
Content-Disposition: attachment; filename="=?UTF-8?B?xb5sdcWlb3XEjWs=?=
	=?UTF-8?B?w70uemlw?="

UEsDBBQ=
----------%S%
Content-Type: application/octet-stream
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="=?UTF-8?B?dmVyeXZlcnl2ZXI=?=
	=?UTF-8?B?eXZlcnl2ZXJ5dmVyeXZlcnl2ZXJ5dmVyeXZlcnl2ZXJ5dmVyeXZlcnk=?=
	=?UTF-8?B?dmVyeXZlcnl2ZXJ5dmVyeXZlcnl2ZXJ5dmVyeXZlcnl2ZXJ5dmVyeXY=?=
	=?UTF-8?B?ZXJ5bG9uZ2VtYWlsLnBkZg==?="

UEsDBBQ=
----------%S%
Content-Type: application/octet-stream
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="=?UTF-8?B?xb4=?="

UEsDBBQ=
----------%S%
Content-Type: application/octet-stream
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename=abc

UEsDBBQ=
----------%S%
Content-Type: application/octet-stream
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="\"\\"

UEsDBBQ=
----------%S%--
EOD
	, TestMailer::$output);
