<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\IO;

use Google_Exception;
use Google_Http_Request;
use Google_IO_Curl;
use Nette\Diagnostics\Debugger;
use Nette\MemberAccessException;
use Nette\ObjectMixin;



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onRequest()
 * @method onResponse()
 * @method onSuccess()
 * @method onError()
 */
class Curl extends Google_IO_Curl
{

	/** @var array callable(Google_Http_Request) */
	public $onRequest = [];

	/** @var array callable(Google_Http_Request) */
	public $onResponse = [];

	/** @var array callable(Google_Http_Request, float $elapsed) */
	public $onSuccess = [];

	/** @var array callable(Google_Http_Request, float $elapsed, Google_Exception) */
	public $onError = [];

	/**
	 * Callbacks
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 * @throws MemberAccessException
	 */
	public function __call($name, $args)
	{
		return ObjectMixin::call($this, $name, $args);
	}

	public function makeRequest(Google_Http_Request $request)
	{
		$this->onRequest($request);
		Debugger::timer(__CLASS__);
		try
		{
			$res = parent::makeRequest($request);
			$this->onSuccess($request, Debugger::timer(__CLASS__));
			$this->onResponse($request);
		}
		catch (Google_Exception $e)
		{
			$this->onError($request, Debugger::timer(__CLASS__), $e);
			$this->onResponse($request);
			throw $e;
		}

		return $res;
	}

}
