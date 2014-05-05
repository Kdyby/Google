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
use Nette;
use Nette\Application\UI\PresenterComponent;
use Nette\Http\UrlScript;
use Nette\Utils\Html;



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

	/** @var Google */
	protected $google;

	/** @var Configuration */
	protected $config;

	protected $currentUrl;

	/**
	 * @param Google $google
	 */
	public function __construct(Google $google)
	{
		$this->google = $google;
		$this->config = $google->config;
		$this->currentUrl = $google->getCurrentUrl();

		$this->monitor('Nette\Application\IPresenter');
		parent::__construct();
	}

	/** @return Google */
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
			$this->google->client->setRedirectUri((string) $this->currentUrl);
		}
	}



	/**
	 * Facebook get's the url for this handle when redirecting to login dialog.
	 * It automatically calls the onResponse event.
	 */
	public function handleResponse()
	{
		$this->onResponse($this);
die;
		if (!empty($this->config->canvasBaseUrl)) {
			$this->presenter->redirectUrl($this->config->canvasBaseUrl);
		}

		$this->presenter->redirect('this');
	}



	/**
	 * @return array
	 */
	public function getQueryParams()
	{
		$data = array(
//			'client_id' => $this->facebook->config->appId,
			'redirect_uri' => (string)$this->currentUrl,
//			'show_error' => $this->showError
		);

		if ($this->display !== NULL) {
			$data['display'] = $this->display;
		}

		return $data;
	}



	/**
	 * @param string $display
	 * @param bool $showError
	 *
	 * @return string
	 */
	public function getUrl()
	{
		$url = clone $this->currentUrl;
		$url->appendQuery($this->getQueryParams());

		return (string) $url;
	}



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
	 * @param string $display
	 * @param bool $showError
	 * @return Html
	 */
	public function getControl($display = NULL, $showError = FALSE)
	{
		return Html::el('a')->href($this->getUrl($display, $showError));
	}

}
