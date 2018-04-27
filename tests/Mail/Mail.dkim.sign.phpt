<?php

/**
 * Test: Nette\Mail\DkimSigner sign.
 */

declare(strict_types=1);

use Nette\Mail\DkimSigner;
use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

if (!extension_loaded('openssl')) {
	Tester\Environment::skip('OpenSSL not installed');
}

$privateKey = <<<'EOT'
-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQCxDQWcHZB2hO5GNSaj7CUVbCw+wkRBZ9xtNCHqrWwqaUozqWkw
50XEe5gtnvjPix3zmOKJQVY15bYIrgRsnHsbMA2TUSZZyYgCpeypSq7Mvp79H1ZF
dHlVRUbjzgLzOAko7Yg3F7vwMS2SWAwZxRttCkDmQyMO0tn2kRE5ZJhFTQIDAQAB
AoGBAJ3mTyp782rAAwD6RgvLfwcsAgm2l8j9J8j8xYLWR7FLVbHdVMMYf1BMKdwF
+0CdgYjOwLpIWuqWg1IaYDe9FswcvAVLvFmbkmbt40oWD0v67SVxITXjjKmaA6yL
TF0QqVp7Wo2Rppi4K0A5JaK9FnsbWygGwdtNmz518Pc5JngBAkEA1xEqnzjAXFB5
4egCaKj3uGpVfGelUFvIlFrxoLSrvZ4NSp0XdrR/lzBB/TsMi/WkZpPeeQUTy+bq
GcIdXYc9IQJBANK/kjfXtg3tK0dNW/9GXcZA2Nb40475rnCruXy8nhv+S8KkccdY
IDnSvSs1ALy3X3Ew+aAGtIeWJAHttihvNq0CQQCTrQTwSd7ERLo8Zbxps0ROTC2g
++Zm1G9Zd00dRZH75PBJgK7g4rYN0aQuRwKphCW8DeMghF0AkPHEeCcD1t4hAkBM
75y8gCY5HU0AYbBlF9YiCwheKkZpWqMhBL/ZVq5Nv97+drQGtxhEo7dlb5sOSc8w
7lUi42/CU8BfZ91pE3idAkB4CvSZyTfH5MTM+ta7oQGq/HGMCB+nIOdy6OZ28nGA
FAXmcXdM0CjhOg4Xnf07+X5iKyXZQ17ErPJgh/L1Ih/O
-----END RSA PRIVATE KEY-----
EOT;

$mail = new Message;
$mail->setFrom('John Doe <doe@example.com>');
$mail->addTo('Lady Jane <jane@example.com>');
$mail->setSubject('Hello Jane!');
$mail->setBody('Příliš žluťoučký kůň');

$signer = new DkimSigner([
	'privateKey' => $privateKey,
	'domain' => 'nette.org',
	'selector' => 'selector',
	'testMode' => true,
], ['From', 'To', 'Subject']);

Assert::match(<<<'EOD'
MIME-Version: 1.0
X-Mailer: Nette Framework
Date: %a%
From: John Doe <doe@example.com>
To: Lady Jane <jane@example.com>
Subject: Hello Jane!
Message-ID: <%a%@%a%>
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
DKIM-Signature: v=1; a=rsa-sha256; q=dns/txt; l=31; s=selector; t=0; c=relaxed/simple; h=From:To:Subject; d=nette.org; bh=ajG6YIACaHQVmGzBb/7kmuYS2aRqla4IYr5sTMwVP7k=; b=l/nd5fGVXwzPZNZFJrn3f7kvFmaFV5cybkBUYzvIoc6hDPNw6750KpBtwsdjvJQ8u7YaEo9kSm7v2CBQj6KVSafGUZ4hDr8Yv18TjOzO9j7iUjdVJulpYq77vNzinQo3cwpSdijbZEBOd+CJwsRyk+OtMG17Yz7sNa8+Xd2Lp+Q=

Příliš žluťoučký kůň
EOD
	, $signer->generateSignedMessage($mail));
