<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\Dialog;

use Nette\Http\UrlScript;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
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
	 * If the user is already in session storage, it will behave, as if were redirected from Google right now,
	 * this means, it will directly call onResponse event.
	 */
	public function handleOpen()
	{
		$this->open();
	}



	/**
	 * @return UrlScript
	 */
	public function getUrl()
	{
		return $this->google->client->createAuthUrl();
	}

}
