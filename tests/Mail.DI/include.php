<?php


class FooExtension extends Nette\DI\CompilerExtension
{}


function createContainer($source, $config = NULL)
{
	$loader = new Nette\DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create($config, 'neon'));
	$class = 'Container' . md5(lcg_value());
	$code = $source->addConfig((array) $config)->setClassName($class)->compile();

	eval($code);
	return new $class;
}
