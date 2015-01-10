<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google;

use Nette\Http\Url;
use Nette\Object;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class Configuration extends Object
{

	/**
	 * @var string
	 */
	public $clientId;

	/**
	 * @var string
	 */
	public $clientSecret;

	/**
	 * @var string
	 */
	public $apiKey;

	/**
	 * @var array
	 */
	public $scopes;

	/**
	 * @var array
	 */
	private $returnDestination;



	/**
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $apiKey
	 * @param array $scopes
	 */
	public function __construct($clientId, $clientSecret, $apiKey, array $scopes = array())
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->apiKey = $apiKey;
		$this->scopes = $scopes;
	}



	/**
	 * @internal
	 * @param \Google_Client $client
	 */
	public function configureClient(\Google_Client $client)
	{
		$client->setClientId($this->clientId);
		$client->setClientSecret($this->clientSecret);
		$client->setScopes($this->scopes);
		$client->setDeveloperKey($this->apiKey);
	}



	/**
	 * Accepts presenter name on which the Dialog component is attached.
	 * You can also specify arguments as if the was the PresenterComponent::link() method.
	 *
	 * Be aware, that the method resets all persistent parameters in the entire component tree.
	 * If you need to really persist them, you have to specify them explicitly.
	 *
	 * @param string $destination
	 * @param array $args
	 * @return Google
	 */
	public function setReturnDestination($destination, $args = array())
	{
		if ($destination === 'this') {
			throw new InvalidArgumentException('Please specify a valid presenter name');
		}

		$this->returnDestination = func_get_args() + array(1 => array());
		return $this;
	}



	/**
	 * @return array
	 */
	public function getReturnDestination()
	{
		if (!$this->returnDestination) {
			throw new InvalidStateException(
				"Google oauth can redirect back only to one exactly specified url (or several, but they still have to be specified), " .
				"so you have to set the action of this url with " . get_called_class() . "::setReturnDestination() or preferably in config under key `google: returnUri:`. " .
				"The format is either an uri `https://www.kdyby.org/oauth-google`, presenter name `:Front:Homepage:` in which case the signal to this component will be added lazily, " .
				"or you can specify parameters `':Front:Homepage:'(page=2)`. Be aware that the presenter name should be always absolute and it's the preferred way to specify the return uri. " .
				"After successful authorization, the user will be redirected back where he started, using the restore request."
			);
		}

		return $this->returnDestination;
	}

}
