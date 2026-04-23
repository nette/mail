<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\Message - setHtmlBody() must not hit PCRE backtrack limit on large data: URIs.
 * Covers all three regex branches protected by the possessive unrolled loop:
 * <img src=...>, <body background=...>, and <div style="...url(...)">.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


// ~500 kB of base64 payload inside a data: URI; default pcre.backtrack_limit is 1 000 000
$payload = str_repeat('A', 500_000);
$dataUri = 'data:image/png;base64,' . $payload;

$cases = [
	'<img src="' . $dataUri . '" alt="x">',
	'<body background="' . $dataUri . '">',
	'<div style="background: url(' . $dataUri . ')">',
];

foreach ($cases as $html) {
	$mail = new Message;
	$mail->setHtmlBody($html, __DIR__ . '/fixtures');
	// data: URI must pass through intact — it is excluded by scheme lookahead, not embedded
	Assert::contains($dataUri, $mail->getHtmlBody());
}
