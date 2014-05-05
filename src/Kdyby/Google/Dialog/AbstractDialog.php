<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\Dialog;

use Kdyby\Google\Configuration;
use Kdyby\Google\Google;
use Nette\Application\UI\PresenterComponent;
use Nette\Http\UrlScript;
use Nette\Utils\Html;
use Nette;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onResponse(AbstractDialog $dialog)
 */
abstract class AbstractDialog extends PresenterComponent
{

	/**
	 * @var array of function(AbstractDialog $dialog)
	 */
	public $onResponse = array();

	/**
	 * @var Google
	 */
	protected $google;

	/**
	 * @var Configuration
	 */
	protected $config;

	/**
	 * @var \Kdyby\Google\SessionStorage
	 */
	protected $session;

	/**
	 * @var \Nette\Http\UrlScript
	 */
	protected $currentUrl;



	/**
	 * @param Google $google
	 */
	public function __construct(Google $google)
	{
		$this->google = $google;
		$this->config = $google->config;
		$this->session = $google->getSession();
		$this->currentUrl = $google->getCurrentUrl();

		$this->monitor('Nette\Application\IPresenter');
		parent::__construct();
	}



	/**
	 * @return Google
	 */
	public function getGoogle()
	{
		return $this->google;
	}



	/**
	 * @param \Nette\ComponentModel\Container $obj
	 */
	protected function attached($obj)
	{
		parent::attached($obj);

		if ($obj instanceof Nette\Application\IPresenter) {
			$this->currentUrl = new UrlScript($this->link('//response!'));
		}
	}



	/**
	 * @return UrlScript
	 */
	abstract public function getUrl();



	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function open()
	{
		$this->presenter->redirectUrl($this->getUrl());
	}



	/**
	 * Opens the dialog.
	 */
	public function handleOpen()
	{
		$this->open();
	}



	/**
	 * Google get's the url for this handle when redirecting to login dialog.
	 * It automatically calls the onResponse event.
	 */
	public function handleResponse()
	{
		$this->google->getUser(); // check the received parameters and save user
		$this->onResponse($this);
		$this->presenter->redirect('this');
	}

}
