<?php

/**
 * Common code for Mail test cases.
 */

declare(strict_types=1);

use Nette\Mail\SmtpMailer;

/**
 * A wrapper for SmtpMailer (derived class) that helps with tesing.
 * It overrides internal connection functions so we can mock TCP communication.
 * Note: this is the first implementation -- it only collects the write operations.
 */
class SmtpMailerTestWrapper extends SmtpMailer
{
	private $connected = false;
	private $written = [];


	public function __construct()
	{
		parent::__construct('localhost', '', '');
	}


	/**
	 * Overrides connection to mock TCP interaction.
	 */
	protected function connect(): void
	{
		if ($this->connected) {
			throw new Exception('The connect() function called, but the connection was already established.');
		}
		$this->connected = true;
	}


	/**
	 * Terminates mocking connection.
	 */
	protected function disconnect(): void
	{
		if (!$this->connected) {
			throw new Exception('The disconnect() function called, but no connection was currently established.');
		}
		$this->connected = false;
	}


	/**
	 * Overrides writing function so we can collect, what was actually written by the sender.
	 */
	protected function write(string $line, int|array|null $expectedCode = null, ?string $message = null): void
	{
		$this->written[] = $line;
	}


	/**
	 * Overrides reading function to mock inputs for the mailer.
	 */
	protected function read(): string
	{
		return ''; // not needed yet, may be implemented in the future
	}


	/**
	 * Return lines collected in write calls.
	 * @return string[]
	 */
	public function getWrittenLines(): array
	{
		return $this->written;
	}
}
