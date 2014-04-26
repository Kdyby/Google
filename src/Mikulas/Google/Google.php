<?php

namespace Mikulas\Google;

use Google_Client;
use Google_Exception;
use Google_Http_Request;
use Google_IO_Abstract;
use Mikulas\Google\Dialog\LoginDialog;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Object;
use Nette\Utils\Json;


/**
 * @property-read Google_Client $client
 */
class Google extends Object
{

	const OPENID_URL = 'https://www.googleapis.com/plus/v1/people/me/openIdConnect';

	/** @var Request */
	protected $httpRequest;

	/** @var Response */
	protected $httpResponse;

	/** @var Configuration */
	protected $config;

	/** @var Google_Client */
	private $client;

	/**
	 * @param Configuration $config
	 * @param Google_Client $client
	 * @param Google_IO_Abstract $io
	 * @param Request $httpRequest
	 * @param Response $httpResponse
	 */
	public function __construct(Configuration $config, Google_Client $client, Google_IO_Abstract $io, Request $httpRequest, Response $httpResponse)
	{
		$this->config = $config;
		$this->httpResponse = $httpResponse;
		$this->httpRequest = $httpRequest;
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
	 * @internal
	 * @return Google_Client
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * @return string
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
		$request = new Google_Http_Request(self::OPENID_URL . '?' . http_build_query([
			'key' => $this->config->apiKey,
		]));
		$request->setRequestHeaders(
			['Authorization' => 'Bearer ' . $this->getAccessToken()]
		);
		return $this->client->execute($request);
	}

	/**
	 * @internal
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}

	public function createLoginDialog()
	{
		return new LoginDialog($this);
	}

}
