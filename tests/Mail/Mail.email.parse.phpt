<?php

/**
 * Test: Nette\Mail\Message email address parsing.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$mail = new Message;

$mail->setFrom('kun1@example.com');
Assert::same(['kun1@example.com' => null], $mail->getFrom());

$mail->setFrom('kun1@example.com', 'Žluťoučký kůň');
Assert::same(['kun1@example.com' => 'Žluťoučký kůň'], $mail->getFrom());

$mail->setFrom('Žluťoučký kůň <kun1@example.com>');
Assert::same(['kun1@example.com' => 'Žluťoučký kůň'], $mail->getFrom());

$mail->setFrom('Žluťoučký "kůň" <kun1@example.com>');
Assert::same(['kun1@example.com' => 'Žluťoučký "kůň"'], $mail->getFrom());

$mail->setFrom('"Žluťoučký kůň" <kun1@example.com>');
Assert::same(['kun1@example.com' => 'Žluťoučký kůň'], $mail->getFrom());

$mail->setFrom('"Žluťouč\"k\ý kůň" <kun1@example.com>');
Assert::same(['kun1@example.com' => 'Žluťouč"ký kůň'], $mail->getFrom());

$mail->setFrom('The\Mail <kun1@example.com>');
Assert::same(['kun1@example.com' => 'TheMail'], $mail->getFrom());

$mail->setFrom('The.Mail <kun1@example.com>');
Assert::same(['kun1@example.com' => 'The.Mail'], $mail->getFrom());
