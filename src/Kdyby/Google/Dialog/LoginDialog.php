<?php

namespace Kdyby\Google\Dialog;


use Nette\Http\Url;


class LoginDialog extends AbstractDialog
{

	/**
	 * Facebook get's the url for this handle when redirecting to login dialog.
	 * It automatically calls the onResponse event.
	 */
	public function handleResponse()
	{
		$this->google->client->authenticate($_GET['code']);

		parent::handleResponse();
	}

	public function getAccessToken()
	{
		return $this->google->client->getAccessToken();
	}

	/**
	 * Checks, if there is a user in storage and if not, it redirects to login dialog.
	 * If the user is already in session storage, it will behave, as if were redirected from facebook right now,
	 * this means, it will directly call onResponse event.
	 */
	public function handleOpen()
	{
		$this->open();
	}

	/**
	 * @return string url
	 */
	public function getUrl()
	{
		return $this->google->client->createAuthUrl();
	}

}
