<?php declare(strict_types=1);

/**
 * Test: Nette\Mail\SmtpMailer encryption validation.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('an unknown encryption is rejected', function () {
	foreach (['TLS', 'SSL', 'starttls', ''] as $encryption) {
		Assert::exception(
			fn() => new Nette\Mail\SmtpMailer('mail.example.com', 'user', 'pass', encryption: $encryption),
			Nette\InvalidArgumentException::class,
			"Encryption must be 'ssl', 'tls' or null, '$encryption' given.",
		);
	}
});
