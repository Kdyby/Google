<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google;


/**
 * Common interface for caching Google exceptions
 *
 * @author Filip Procházka <email@filip-prochazka.com>
 */
interface Exception
{

}



/**
 * @author Filip Procházka <email@filip-prochazka.com>
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class InvalidStateException extends \RuntimeException implements Exception
{

}
