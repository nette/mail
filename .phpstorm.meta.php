<?php

declare(strict_types=1);

namespace PHPSTORM_META;

expectedArguments(\Nette\Mail\Message::setPriority(), 0, \Nette\Mail\Message::High, \Nette\Mail\Message::Normal, \Nette\Mail\Message::Low);
expectedReturnValues(\Nette\Mail\Message::getPriority(), \Nette\Mail\Message::High, \Nette\Mail\Message::Normal, \Nette\Mail\Message::Low);
