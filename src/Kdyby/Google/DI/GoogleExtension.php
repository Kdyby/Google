<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\DI;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\Utils\Validators;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class GoogleExtension extends CompilerExtension
{

	/** @var array */
	public $defaults = [
		'clientId' => NULL,
		'clientSecret' => NULL,
		'apiKey' => NULL,
		'clearAllWithLogout' => FALSE,
		'scopes' => ['profile', 'email'],
	];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['clientId'], 'string', 'Client ID');
		Validators::assert($config['clientSecret'], 'string:24', 'Client secret');
		Validators::assert($config['apiKey'], 'string:39', 'API Key');
		Validators::assert($config['scopes'], 'list', 'Permission scopes');

		$configurator = $builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Google\Configuration')
			->setArguments([
				$config['clientId'],
				$config['clientSecret'],
				$config['apiKey'],
				$config['scopes'],
			])
			->setInject(FALSE);

		$builder->addDefinition($this->prefix('client'))
			->setClass('Google_Client')
			->addSetup('setClientId', [$config['clientId']])
			->addSetup('setClientSecret', [$config['clientSecret']])
			->addSetup('setScopes', [$config['scopes']])
			->setInject(FALSE);

		$curl = $builder->addDefinition($this->prefix('curl'))
			->setClass('Kdyby\Google\IO\Curl')
			->addSetup($this->prefix('@client') . '::setIo', ['@self'])
			->setInject(FALSE);

		if ($builder->parameters['debugMode'])
		{
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Google\Diagnostics\Panel')
				->setInject(FALSE);
			$curl->addSetup($this->prefix('@panel') . '::register', ['@self']);
		}

		$builder->addDefinition($this->prefix('google'))
			->setClass('Kdyby\Google\Google')
			->setInject(FALSE);

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->clearAll(); }', [
					'@container', '@self', $this->prefix('session')
				]);
		}
	}



	/**
	 * @param \Nette\Configurator $configurator
	 */
	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('google', new GoogleExtension());
		};
	}

}
