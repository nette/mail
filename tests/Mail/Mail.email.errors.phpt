<?php

/**
 * Test: Nette\Mail\Message invalid email addresses.
 */

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message();

Assert::exception(function () use ($mail) {
	// From
	$mail->setFrom('John Doe <doe@example. com>');
}, Nette\Utils\AssertionException::class, "The header 'From' expects to be email, string 'doe@example. com' given.");


Assert::exception(function () use ($mail) {
	$mail->setFrom('John Doe <>');
}, Nette\Utils\AssertionException::class, "The header 'From' expects to be email, string '' given.");


Assert::exception(function () use ($mail) {
	$mail->setFrom('John Doe <doe@examplecom>');
}, Nette\Utils\AssertionException::class, "The header 'From' expects to be email, string 'doe@examplecom' given.");


Assert::exception(function () use ($mail) {
	$mail->setFrom('John Doe');
}, Nette\Utils\AssertionException::class, "The header 'From' expects to be email, string 'John Doe' given.");


Assert::exception(function () use ($mail) {
	$mail->setFrom('doe;@examplecom');
}, Nette\Utils\AssertionException::class, "The header 'From' expects to be email, string 'doe;@examplecom' given.");


Assert::exception(function () use ($mail) {
	// addReplyTo
	$mail->addReplyTo('@');
}, Nette\Utils\AssertionException::class, "The header 'Reply-To' expects to be email, string '@' given.");


Assert::exception(function () use ($mail) {
	// addTo
	$mail->addTo('@');
}, Nette\Utils\AssertionException::class, "The header 'To' expects to be email, string '@' given.");


Assert::exception(function () use ($mail) {
	// addCc
	$mail->addCc('@');
}, Nette\Utils\AssertionException::class, "The header 'Cc' expects to be email, string '@' given.");


Assert::exception(function () use ($mail) {
	// addBcc
	$mail->addBcc('@');
}, Nette\Utils\AssertionException::class, "The header 'Bcc' expects to be email, string '@' given.");
