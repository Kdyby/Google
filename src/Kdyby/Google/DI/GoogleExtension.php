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
		'appId' => NULL, // kdyby-style naming
		'appSecret' => NULL,
		'clientId' => NULL, // google-style naming
		'clientSecret' => NULL,
		'apiKey' => NULL,
		'clearAllWithLogout' => TRUE,
		'scopes' => array('profile', 'email'),
		'accessType' => 'online',
		'returnUri' => NULL,
		'debugger' => '%debugMode%'
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$rawConfig = $this->getConfig();
		if (isset($rawConfig['appId']) || isset($rawConfig['appSecret'])) {
			if (isset($rawConfig['clientId']) || isset($rawConfig['clientSecret'])) {
				throw new Nette\Utils\AssertionException("Use only one syntax, either appId and appSecret or clientId and clientSecret, do not combine them");
			}

		} else {
			$config['appId'] = $rawConfig['clientId'];
			$config['appSecret'] = $rawConfig['clientSecret'];
			unset($config['clientId'], $config['clientSecret']);
		}

		Validators::assert($config['appId'], 'string', 'App ID');
		Validators::assert($config['appSecret'], 'string:24', 'App secret');
		Validators::assert($config['apiKey'], 'string:39|null', 'API Key');
		Validators::assert($config['scopes'], 'list', 'Permission scopes');
		if (!in_array($config['accessType'], $allowed = array('online', 'offline'))) {
			throw new Nette\Utils\AssertionException("Key accessType is expected to be one of [" . implode(', ', $allowed) . "], but '" . $config['accessType'] . "' was given.");
		}

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Google\Google');

		$configuration = $builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Google\Configuration')
			->setArguments(array(
				$config['appId'],
				$config['appSecret'],
				$config['apiKey'],
				$config['scopes'],
			));

		if ($config['returnUri'] instanceof \stdClass) { // was an neon entity, must be valid presenter name with parameters
			$destination = $config['returnUri']->value;

			if (!self::isPresenterName($destination)) { // presenter name
				throw new Nette\Utils\AssertionException("Please fix your configuration, expression '$destination' does not look like a valid presenter name.");
			}

			$configuration->addSetup('setReturnDestination', array($destination, $config['returnUri']->attributes));

		} elseif ($config['returnUri'] instanceof Statement) { // was an neon entity, must be valid presenter name with parameters
			$destination = $config['returnUri']->entity;

			if (!self::isPresenterName($destination)) { // presenter name
				throw new Nette\Utils\AssertionException("Please fix your configuration, expression '$destination' does not look like a valid presenter name.");
			}

			$configuration->addSetup('setReturnDestination', array($destination, $config['returnUri']->arguments));

		} elseif ($config['returnUri'] !== NULL) { // must be a valid uri or presenter name
			$destination = NULL;

			if (self::isUrl($config['returnUri'])) {
				$destination = new Nette\Http\UrlScript($config['returnUri']);
				if (!$destination->scheme) {
					$fixed = clone $destination;
					$fixed->scheme = 'https';
					throw new Nette\Utils\AssertionException("Please fix your configuration, scheme for returnUri is missing. Hint: `" . $this->name . ": returnUri: $fixed`");
				}

				if (!$destination->path) {
					$fixed = clone $destination;
					$fixed->path = '/oauth-google';
					throw new Nette\Utils\AssertionException("Are you sure that you wanna redirect from Google auth to '$destination'? Hint: you might wanna add some path `" . $this->name . ": returnUri: $fixed`");
				}

				$destination = new Statement('Nette\Http\UrlScript', array((string) $destination));

			} elseif (!self::isPresenterName($config['returnUri'])) { // presenter name
				throw new Nette\Utils\AssertionException("Please fix your configuration, expression '{$config['returnUri']}' does not look like a valid presenter name.");

			} else { // presenter name
				$destination = $config['returnUri'];
			}

			$configuration->addSetup('setReturnDestination', array($destination));
		}

		$builder->addDefinition($this->prefix('apiClient'))
			->setClass('Google_Client')
			->addSetup('$this->addService(?, ?)', array($this->prefix('apiClient'), '@self'))
			->addSetup('?->configureClient(?)', array($this->prefix('@config'), '@self'))
			->addSetup('setAccessType', array($config['accessType']));

//		$curl = $builder->addDefinition($this->prefix('apiIo'))
//			->setClass('Kdyby\Google\IO\Curl');

		$builder->addDefinition($this->prefix('session'))
			->setClass('Kdyby\Google\SessionStorage');

//		if ($config['debugger']) {
//			$builder->addDefinition($this->prefix('panel'))
//				->setClass('Kdyby\Google\Diagnostics\Panel');
//			$curl->addSetup($this->prefix('@panel') . '::register', array('@self'));
//		}

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->clearAll(); }', array(
					'@container', '@self', $this->prefix('session')
				));
		}
	}



	/**
	 * @param string $value
	 * @return bool
	 */
	protected function isUrl($value)
	{
		$alpha = "a-z\x80-\xFF";
		$domain = "[0-9$alpha](?:[-0-9$alpha]{0,61}[0-9$alpha])?";
		$topDomain = "[$alpha](?:[-0-9$alpha]{0,17}[$alpha])?";
		return (bool) preg_match("(^(https?://)?(?:(?:$domain\\.)*$topDomain|\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}|\\[[0-9a-f:]{3,39}\\])(:\\d{1,5})?(/\\S*)?\\z)i", $value);
	}



	/**
	 * @param string $value
	 * @return bool
	 */
	protected function isPresenterName($value)
	{
		return (bool) preg_match('~^(?:\\/\\/)?\\:?[a-z0-9][a-z0-9:]+$~i', $value);
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
