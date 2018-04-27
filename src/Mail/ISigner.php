<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2018 Lukáš Piják (https://lukaspijak.com)
 */

declare(strict_types=1);

namespace Nette\Mail;


/**
 * Signer interface.
 */
interface ISigner
{

	/**
	 * @param Message $message
	 * @return string
	 * @throws SignException
	 */
	public function generateSignedMessage(Message $message): string;
}
