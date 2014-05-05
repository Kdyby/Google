<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\Diagnostics;

use Google_Exception;
use Google_Http_Request;
use Kdyby\Google\Google;
use Kdyby\Google\IO\Curl;
use Nette;
use Nette\Utils\Html;
use Tracy\Debugger;
use Tracy\IBarPanel;



if (!class_exists('Tracy\Debugger')) {
	class_alias('Nette\Diagnostics\Debugger', 'Tracy\Debugger');
}

if (!class_exists('Tracy\Bar')) {
	class_alias('Nette\Diagnostics\Bar', 'Tracy\Bar');
	class_alias('Nette\Diagnostics\BlueScreen', 'Tracy\BlueScreen');
	class_alias('Nette\Diagnostics\Helpers', 'Tracy\Helpers');
	class_alias('Nette\Diagnostics\IBarPanel', 'Tracy\IBarPanel');
}

if (!class_exists('Tracy\Dumper')) {
	class_alias('Nette\Diagnostics\Dumper', 'Tracy\Dumper');
}



/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property callable $begin
 * @property callable $failure
 * @property callable $success
 */
class Panel extends Nette\Object implements IBarPanel
{

	/**
	 * @var int logged time
	 */
	private $totalTime = 0;

	/**
	 * @var array
	 */
	private $calls = [];

	/**
	 * @var \stdClass
	 */
	private $current;



	/**
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('img')->height('16')->src('data:image/png;base64,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');
		$tab = Html::el('span')->title('Google')->add($img);
		$title = Html::el()->setText('Google');
		if ($this->calls) {
			$title->setText(
				count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
			);
		}
		return (string)$tab->add($title);
	}



	/**
	 * @return string
	 */
	public function getPanel()
	{
		if (!$this->calls) {
			return NULL;
		}

		ob_start();
		$esc = callback('Nette\Templating\Helpers::escapeHtml');
		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) { return \Tracy\Dumper::toHtml($o, array('collapse' => $c)); }
			: callback('\Tracy\Helpers::clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		require_once __DIR__ . '/panel.phtml';
		return ob_get_clean();
	}

	public function begin(Google_Http_Request $request)
	{
		if ($this->current) return;
		$this->calls[] = $this->current = (object)[
			'url' => $request->getUrl(),
			'params' => $request->getQueryParams(),
			'result' => NULL,
			'exception' => NULL,
			'info' => [],
			'time' => 0,
		];
	}

	public function response(Google_Http_Request $request, $elapsed)
	{
		$this->totalTime += $this->current->time = $elapsed;
		$this->current->params = $request->getQueryParams();
		$this->current->info['method'] = $request->getRequestMethod();
	}

	/**
	 * @param \Google_Http_Request $request
	 * @param $elapsed
	 */
	public function success(Google_Http_Request $request, $elapsed)
	{
		if (!$this->current) return;
		$this->response($request, $elapsed);
		try {
			$result = Nette\Utils\Json::decode($request->getResponseBody());

		} catch (Nette\Utils\JsonException $e) {
			$result = $request->getResponseBody();
		}

		$this->current->result = $result;

		$this->current = NULL;
	}


	/**
	 * @param Google_Http_Request $request
	 * @param float $elapsed
	 * @param Google_Exception $exception
	 */
	public function error(Google_Http_Request $request, $elapsed, Google_Exception $exception)
	{
		if (!$this->current) return;
		$this->response($request, $elapsed);
		$this->current->exception = $exception;

		$this->current = NULL;
	}



	public function register(Curl $client)
	{
		$client->onRequest[] = $this->begin;
		$client->onError[] = $this->error;
		$client->onSuccess[] = $this->success;

		self::getDebuggerBar()->addPanel($this);
	}



	/**
	 * @return \Tracy\Bar
	 */
	private static function getDebuggerBar()
	{
		return method_exists('Tracy\Debugger', 'getBar') ? Debugger::getBar() : Debugger::$bar;
	}

}
