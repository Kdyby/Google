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
use Nette\Utils\Html;
use Nette;
use Psr\Http\Message\RequestInterface;
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
	private $calls = array();

	/**
	 * @var \stdClass
	 */
	private $current;



	/**
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('img')->height('16')->src('data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/developers-logo.png')));
		$tab = Html::el('span')->title('Google')->addHtml($img);
		$title = Html::el()->setText('Google');
		if ($this->calls) {
			$title->setText(
				count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
			);
		}
		return (string) $tab->addText($title);
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
		$esc = array('Nette\Templating\Helpers', 'escapeHtml');
		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) {
				return \Tracy\Dumper::toHtml($o, array('collapse' => $c));
			}
			: array('\Tracy\Helpers', 'clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		require __DIR__ . '/panel.phtml';
		return ob_get_clean();
	}



	public function begin(RequestInterface $request)
	{
		if ($this->current) {
			return;
		}
		$this->calls[] = $this->current = (object) array(
			'url' => $request->getUrl(),
			'params' => $request->getQueryParams(),
			'result' => NULL,
			'exception' => NULL,
			'info' => array(),
			'time' => 0,
		);
	}



	public function response(RequestInterface $request, $elapsed)
	{
		$this->totalTime += $this->current->time = $elapsed;
		$this->current->params = $request->getQueryParams();
		$this->current->info['method'] = $request->getRequestMethod();
	}



	/**
	 * @param RequestInterface $request
	 * @param $elapsed
	 */
	public function success(RequestInterface $request, $elapsed)
	{
		if (!$this->current) {
			return;
		}
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
	 * @param RequestInterface $request
	 * @param float $elapsed
	 * @param Google_Exception $exception
	 */
	public function error(RequestInterface $request, $elapsed, Google_Exception $exception)
	{
		if (!$this->current) {
			return;
		}
		$this->response($request, $elapsed);
		$this->current->exception = $exception;

		$this->current = NULL;
	}



	public function register(GuzzleHttpHandler $client)
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
