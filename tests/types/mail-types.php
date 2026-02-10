<?php

/**
 * PHPStan type tests.
 */

declare(strict_types=1);

use Nette\Mail\Message;
use Nette\Mail\MimePart;
use function PHPStan\Testing\assertType;


function testMimePartGetHeader(MimePart $part): void
{
	assertType('array<string, string|null>|string|null', $part->getHeader('Content-Type'));
}


function testMimePartGetHeaders(MimePart $part): void
{
	assertType('array<string, array<string, string|null>|string>', $part->getHeaders());
}


function testMimePartGetEncoding(MimePart $part): void
{
	assertType('string', $part->getEncoding());
}


function testMessageGetFrom(Message $message): void
{
	assertType('array<string, string|null>|null', $message->getFrom());
}


function testMessageGetSubject(Message $message): void
{
	assertType('string|null', $message->getSubject());
}


function testMessageGetReturnPath(Message $message): void
{
	assertType('string|null', $message->getReturnPath());
}


function testMessageGetPriority(Message $message): void
{
	assertType('int|null', $message->getPriority());
}


function testMessageGetAttachments(Message $message): void
{
	assertType('list<Nette\Mail\MimePart>', $message->getAttachments());
}
