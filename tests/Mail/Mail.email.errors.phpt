<?php

/**
 * Test: Nette\Mail\Message invalid email addresses.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message;

Assert::exception(
	fn() => $mail->setFrom('John Doe <doe@example. com>'),
	Nette\Utils\AssertionException::class,
	"The header 'From' expects to be email, string 'doe@example. com' given.",
);


Assert::exception(
	fn() => $mail->setFrom('John Doe <>'),
	Nette\Utils\AssertionException::class,
	"The header 'From' expects to be email, string '' given.",
);


Assert::exception(
	fn() => $mail->setFrom('John Doe <doe@examplecom>'),
	Nette\Utils\AssertionException::class,
	"The header 'From' expects to be email, string 'doe@examplecom' given.",
);


Assert::exception(
	fn() => $mail->setFrom('John Doe'),
	Nette\Utils\AssertionException::class,
	"The header 'From' expects to be email, string 'John Doe' given.",
);


Assert::exception(
	fn() => $mail->setFrom('doe;@examplecom'),
	Nette\Utils\AssertionException::class,
	"The header 'From' expects to be email, string 'doe;@examplecom' given.",
);


Assert::exception(
	fn() => $mail->addReplyTo('@'),
	Nette\Utils\AssertionException::class,
	"The header 'Reply-To' expects to be email, string '@' given.",
);


Assert::exception(
	fn() => $mail->addTo('@'),
	Nette\Utils\AssertionException::class,
	"The header 'To' expects to be email, string '@' given.",
);


Assert::exception(
	fn() => $mail->addCc('@'),
	Nette\Utils\AssertionException::class,
	"The header 'Cc' expects to be email, string '@' given.",
);


Assert::exception(
	fn() => $mail->addBcc('@'),
	Nette\Utils\AssertionException::class,
	"The header 'Bcc' expects to be email, string '@' given.",
);
