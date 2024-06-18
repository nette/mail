<?php

declare(strict_types=1);


class FooExtension extends Nette\DI\CompilerExtension
{
}


function createContainer($source, $config = null): Nette\DI\Container
{
	$loader = new Nette\DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create($config, 'neon'));
	$class = 'Container' . @++$GLOBALS['counter'];
	$code = $source->addConfig((array) $config)->setClassName($class)->compile();

	eval($code);
	return new $class;
}
