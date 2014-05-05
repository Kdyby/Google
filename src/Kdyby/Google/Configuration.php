<?php

namespace Kdyby\Google;

use Nette\Object;


class Configuration extends Object
{

	/** @var string */
	public $clientId;

	/** @var string */
	public $clientSecret;

	/** @var string */
	public $apiKey;

	/** @var array */
	public $scopes;

	/**
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $apiKey
	 * @param array $scopes
	 */
	public function __construct($clientId, $clientSecret, $apiKey, $scopes)
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->apiKey = $apiKey;
		$this->scopes = $scopes;
	}

}
