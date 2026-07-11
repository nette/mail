<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Mail;

use Nette;
use function date;


/**
 * Writes emails to .eml files instead of sending them. Intended for development and tests, where
 * mail must not leave the machine but still needs to be inspected: the files open in any mail client.
 */
class FileMailer implements Mailer
{
	private ?Signer $signer = null;
	private ?string $lastPath = null;


	public function __construct(
		private readonly string $dir,
	) {
	}


	public function setSigner(Signer $signer): static
	{
		$this->signer = $signer;
		return $this;
	}


	/**
	 * Writes the email into the directory.
	 * @throws SendException
	 */
	public function send(Message $mail): void
	{
		$data = $this->signer
			? $this->signer->generateSignedMessage($mail)
			: $mail->generateMessage();

		// sorts chronologically in a listing; the random part keeps messages sent within one second apart
		$path = $this->dir . '/' . date('Y-m-d-H-i-s') . '-' . Nette\Utils\Random::generate(8) . '.eml';

		try {
			Nette\Utils\FileSystem::write($path, $data);
		} catch (Nette\IOException $e) {
			throw new SendException("Unable to write email to '$path'.", previous: $e);
		}

		$this->lastPath = $path;
	}


	/**
	 * Returns the path of the message written last, or null if nothing has been written yet.
	 */
	public function getLastPath(): ?string
	{
		return $this->lastPath;
	}
}
