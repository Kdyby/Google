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
use Kdyby\Google\IO\Curl;
use Nette\Utils\Html;
use Nette;
use Tracy\Debugger;
use Tracy\IBarPanel;


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
        $img = Html::el('img')
            ->appendAttribute('style', 'height: 16px;')
            ->appendAttribute('src', 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/developers-logo.png')));
        $tab = Html::el('span')
            ->appendAttribute('title','Google')
            ->addHtml($img);
        $title = Html::el()->setText('Google');
        if ($this->calls) {
            $title->setText(
                count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
                ' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
            );
        }
        return (string)$tab->addHtml($title);
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
            ? function ($o, $c = FALSE) {
                return \Tracy\Dumper::toHtml($o, array('collapse' => $c));
            }
            : callback('\Tracy\Helpers::clickableDump');
        $totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

        require __DIR__ . '/panel.phtml';
        return ob_get_clean();
    }


    public function begin(Google_Http_Request $request)
    {
        if ($this->current) {
            return;
        }
        $this->calls[] = $this->current = (object)array(
            'url' => $request->getUrl(),
            'params' => $request->getQueryParams(),
            'result' => NULL,
            'exception' => NULL,
            'info' => array(),
            'time' => 0,
        );
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
     * @param Google_Http_Request $request
     * @param float $elapsed
     * @param Google_Exception $exception
     */
    public function error(Google_Http_Request $request, $elapsed, Google_Exception $exception)
    {
        if (!$this->current) {
            return;
        }
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
