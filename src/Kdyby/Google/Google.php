<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google;

use Google_Client;
use Google_Exception;
use Google_Http_Request;
use Google_IO_Abstract;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Object;
use Nette\Utils\Json;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property-read Google_Client $client
 */
class Google extends Object
{

	/** @var Request */
	protected $httpRequest;

	/**
	 * @var Configuration
	 */
	protected $config;

	/**
	 * @var SessionStorage
	 */
	private $session;

	/**
	 * @var Google_Client
	 */
	private $client;



	public function __construct(
		Configuration $config, Request $httpRequest, SessionStorage $session,
		Google_Client $client, Google_IO_Abstract $io)
	{
		$this->config = $config;
		$this->httpRequest = $httpRequest;
		$this->session = $session;

		$this->client = $client;
		$this->client->setIo($io);
	}



	/**
	 * @internal
	 * @return UrlScript The current URL
	 */
	public function getCurrentUrl()
	{
		return $this->httpRequest->url;
	}



	/**
	 * @return Google_Client
	 */
	public function getClient()
	{
		return $this->client;
	}



	/**
	 * @internal
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}



	/**
	 * @return SessionStorage
	 */
	public function getSession()
	{
		return $this->session;
	}



	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return string The access token
	 */
	public function getAccessToken()
	{
		return Json::decode($this->client->getAccessToken(), TRUE)['access_token'];
	}

	/**
	 * @return array
	 * @throws Google_Exception
	 */
	public function getIdentity()
	{
		$request = new Google_Http_Request((string) $this->config->getOpenIdUrl());
		$request->setRequestHeaders(array(
			'Authorization' => 'Bearer ' . $this->getAccessToken()
		));

		return $this->client->execute($request);
	}



	/**
	 * @return Dialog\LoginDialog
	 */
	public function createLoginDialog()
	{
		return new Dialog\LoginDialog($this);
	}

}
