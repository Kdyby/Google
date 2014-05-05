<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Statement;
use Nette\Utils\Validators;
use Nette;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class GoogleExtension extends CompilerExtension
{

	/** @var array */
	public $defaults = array(
		'clientId' => NULL,
		'clientSecret' => NULL,
		'apiKey' => NULL,
		'clearAllWithLogout' => TRUE,
		'scopes' => array('profile', 'email'),
		'debugger' => '%debugMode%'
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['clientId'], 'string', 'Client ID');
		Validators::assert($config['clientSecret'], 'string:24', 'Client secret');
		Validators::assert($config['apiKey'], 'string:39', 'API Key');
		Validators::assert($config['scopes'], 'list', 'Permission scopes');

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Google\Google');

		$builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Google\Configuration')
			->setArguments(array(
				$config['clientId'],
				$config['clientSecret'],
				$config['apiKey'],
				$config['scopes'],
			));

		$builder->addDefinition($this->prefix('apiClient'))
			->setClass('Google_Client')
			->addSetup('setClientId', array(
				new Statement('?->clientId', array($this->prefix('@config')))
			))
			->addSetup('setClientSecret', array(
				new Statement('?->clientSecret', array($this->prefix('@config')))
			))
			->addSetup('setScopes', array(
				new Statement('?->scopes', array($this->prefix('@config')))
			));

		$curl = $builder->addDefinition($this->prefix('curl'))
			->setClass('Kdyby\Google\IO\Curl')
			->addSetup($this->prefix('@apiClient') . '::setIo', array('@self'));

		$builder->addDefinition($this->prefix('session'))
			->setClass('Kdyby\Google\SessionStorage');

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Google\Diagnostics\Panel');
			$curl->addSetup($this->prefix('@panel') . '::register', array('@self'));
		}

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->clearAll(); }', array(
					'@container', '@self', $this->prefix('session')
				));
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
