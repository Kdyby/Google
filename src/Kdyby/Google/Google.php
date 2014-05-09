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
use Google_IO_Abstract;
use Kdyby\Google\Dialog\AbstractDialog;
use Nette\Application;
use Nette\Application\UI\PresenterComponent;
use Nette\ComponentModel\IComponent;
use Nette\Http\Request;
use Nette\Http\Url;
use Nette\Http\UrlScript;
use Nette\Object;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;



if (!class_exists('Tracy\Debugger')) {
	class_alias('Nette\Diagnostics\Debugger', 'Tracy\Debugger');
}

/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property-read Google_Client $client
 */
class Google extends Object
{

	/**
	 * @var \Nette\Application\Application
	 */
	private $app;

	/**
	 * @var Request
	 */
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

	/**
	 * The ID of the Google user, or 0 if the user is logged out.
	 * @var integer
	 */
	protected $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization code.
	 * null means the access token has yet to be determined.
	 * @var array
	 */
	protected $accessToken;



	public function __construct(
		Application\Application $app, Configuration $config, Request $httpRequest, SessionStorage $session,
		Google_Client $client, Google_IO_Abstract $io)
	{
		$this->app = $app;
		$this->config = $config;
		$this->httpRequest = $httpRequest;
		$this->session = $session;

		$this->client = $client;
		$this->client->setIo($io);
	}



	/**
	 * @return Google_Client
	 */
	public function getClient()
	{
		if ($token = $this->getAccessToken()) {
			$this->client->setAccessToken(json_encode($token));
		}

		$this->client->setRedirectUri((string) $this->getCurrentUrl()->setQuery(''));

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
	 * Sets the access token for api calls.  Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param array|string $token an access token.
	 * @throws InvalidArgumentException
	 * @return Google
	 */
	public function setAccessToken($token)
	{
		if (!is_array($token)) {
			try {
				$token = Json::decode($token, Json::FORCE_ARRAY);

			} catch (JsonException $e) {
				throw new InvalidArgumentException($e->getMessage(), 0, $e);
			}
		}

		if (!isset($token['access_token'])) {
			throw new InvalidArgumentException("It's required that the token has 'access_token' field.");
		}

		$this->accessToken = $token;
		return $this;
	}



	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @param string $key
	 * @return array|string The access token
	 */
	public function getAccessToken($key = NULL)
	{
		if ($this->accessToken === NULL && ($accessToken = $this->getUserAccessToken())) {
			$this->setAccessToken($accessToken);
		}

		if ($key !== NULL) {
			return array_key_exists($key, $this->accessToken) ? $this->accessToken[$key] : NULL;
		}

		return $this->accessToken;
	}



	/**
	 * @param string $key
	 * @return string|bool|NULL
	 */
	public function getIdToken($key = NULL)
	{
		if (!$this->getUser() || !($verifiedIdToken = $this->getVerifiedIdToken())) {
			return NULL;
		}

		if ($key !== NULL) {
			return array_key_exists($key, $verifiedIdToken) ? $verifiedIdToken[$key] : NULL;
		}

		return $verifiedIdToken;
	}



	/**
	 * @return \Google_Service_Oauth2_Userinfoplus
	 * @throws Google_Exception
	 */
	public function getProfile()
	{
		$identity = new \Google_Service_Oauth2($this->getClient());
		return $identity->userinfo->get();
	}



	/**
	 * Get the UID of the connected user, or 0 if the Google user is not connected.
	 *
	 * @return string the UID if available.
	 */
	public function getUser()
	{
		if ($this->user === NULL) {
			$this->user = $this->getUserFromAvailableData();
		}

		return $this->user;
	}



	/**
	 * Determines and returns the user access token, first using
	 * the signed request if present, and then falling back on
	 * the authorization code if present.  The intent is to
	 * return a valid user access token, or false if one is determined
	 * to not be available.
	 *
	 * @return array A valid user access token, or false if one could not be determined.
	 */
	protected function getUserAccessToken()
	{
		if (($code = $this->getCode()) && $code != $this->session->code) {
			if ($accessToken = $this->getAccessTokenFromCode($code)) {
				$this->session->code = $code;
				$this->session->verified_id_token = NULL;
				return $this->session->access_token = $accessToken;
			}

			// code was bogus, so everything based on it should be invalidated.
			$this->session->clearAll();
			return FALSE;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return $this->session->access_token;
	}



	/**
	 * @param string $code An authorization code.
	 * @return array An access token exchanged for the authorization code, or false if an access token could not be generated.
	 */
	protected function getAccessTokenFromCode($code)
	{
		if (empty($code)) {
			return FALSE;
		}

		try {
			$this->client->setRedirectUri((string) $this->getCurrentUrl()->setQuery(''));
			$response = Json::decode($this->client->authenticate($code), Json::FORCE_ARRAY);

			if (empty($response) || !is_array($response)) {
				return FALSE;
			}

			return $response;

		} catch (\Exception $e) {
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return FALSE;
		}
	}



	/**
	 * Determines the connected user by first examining any signed
	 * requests, then considering an authorization code, and then
	 * falling back to any persistent store storing the user.
	 *
	 * @return integer The id of the connected Google user, or 0 if no such user exists.
	 */
	protected function getUserFromAvailableData()
	{
		$user = $this->session->get('user_id', 0);

		// use access_token to fetch user id if we have a user access_token, or if the cached access token has changed.
		if (($accessToken = $this->getAccessToken()) && !($user && $this->session->access_token === $accessToken) ) {
			if (!$user = $this->getUserFromAccessToken()) {
				$this->session->clearAll();

			} else {
				$this->session->user_id = $user;
			}
		}

		return $user;
	}



	/**
	 * Get the authorization code from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization code was
	 * discoverable.
	 *
	 * @return mixed The authorization code, or false if the authorization code could not be determined.
	 */
	protected function getCode()
	{
		$state = $this->getRequest('state');
		if (($code = $this->getRequest('code')) && $state && $this->session->state === $state) {
			$this->session->state = NULL; // CSRF state has done its job, so clear it
			return $code;
		}

		return FALSE;
	}



	/**
	 * Retrieves the UID with the understanding that $this->accessToken has already been set and is seemingly legitimate.
	 * It relies on Google's API to retrieve user information and then extract the user ID.
	 *
	 * @return integer Returns the UID of the Google user, or 0 if the Google user could not be determined.
	 */
	protected function getUserFromAccessToken()
	{
		try {
			if (!$verifiedIdToken = $this->getVerifiedIdToken()) {
				return 0;
			}

			if (!array_key_exists(\Google_Auth_LoginTicket::USER_ATTR, $verifiedIdToken)) {
				return 0;
			}

			return $verifiedIdToken[\Google_Auth_LoginTicket::USER_ATTR];

		} catch (\Exception $e) {
			Debugger::log($e, 'google');
		}

		return 0;
	}



	/**
	 * @return array|NULL
	 */
	protected function getVerifiedIdToken()
	{
		if (!$token = $this->getAccessToken()) {
			return NULL;
		}

		if (!empty($this->session->verified_id_token['payload'])) {
			return $this->session->verified_id_token['payload'];
		}

		$this->client->setRedirectUri((string) $this->getCurrentUrl()->setQuery(''));

		/** @var \Google_Auth_OAuth2 $auth */
		$auth = $this->client->getAuth();

		// ensure the token is set
		$auth->setAccessToken(json_encode($token));

		$loginTicket = $auth->verifyIdToken();
		$this->session->verified_id_token = $loginTicket->getAttributes();

		if (!array_key_exists('payload', $this->session->verified_id_token)) {
			$this->session->verified_id_token = NULL;
			return NULL;
		}

		return $this->session->verified_id_token['payload'];
	}



	/**
	 * Destroy the current session
	 */
	public function destroySession()
	{
		$this->accessToken = NULL;
		$this->user = NULL;
		$this->session->clearAll();
	}



	/**
	 * @return Dialog\LoginDialog
	 */
	public function createLoginDialog()
	{
		return new Dialog\LoginDialog($this);
	}



	/**
	 * @internal
	 * @return UrlScript The current URL
	 */
	public function getCurrentUrl()
	{
		return new UrlScript((string) $this->getReturnLink());
	}



	/**
	 * @param AbstractDialog $dialog
	 * @return Application\UI\Link|UrlScript
	 */
	public function getReturnLink(AbstractDialog $dialog = NULL)
	{
		$destination = $this->config->getReturnDestination();

		if ($destination[0] instanceof Url) {
			return $destination[0];
		}

		$reset = array();

		/** @var Application\UI\Presenter $presenter */
		$presenter = $this->app->getPresenter();

		/** @var PresenterComponent $parent */
		$parent = $dialog ? $dialog->getParent() : $presenter;

		do {
			$prefix = $parent instanceof Application\IPresenter ? '' : $parent->lookupPath('Nette\Application\IPresenter');

			foreach ($parent->getReflection()->getPersistentParams() as $name => $meta) {
				$reset[($prefix ? $prefix . IComponent::NAME_SEPARATOR : '') . $name] = array_key_exists('def', $meta) ? $meta['def'] : NULL;
			}

		} while ($parent = $parent->getParent());

		$args = is_array($destination[1]) ? $destination[1] : array_slice($destination, 1);

		if ($dialog !== NULL) {
			$args['do'] = $dialog->lookupPath('Nette\Application\IPresenter') . IComponent::NAME_SEPARATOR . 'response';
		}

		return $presenter->lazyLink('//' . ltrim($destination[0], '/'), $args + $reset);
	}



	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed|null
	 */
	protected function getRequest($key, $default = NULL)
	{
		if ($value = $this->httpRequest->getPost($key)) {
			return $value;
		}

		if ($value = $this->httpRequest->getQuery($key)) {
			return $value;
		}

		return $default;
	}

}
