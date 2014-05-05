<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google;

use Nette\Object;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
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
