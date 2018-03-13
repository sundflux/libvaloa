<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Libvaloa\I18n;

use Libvaloa\Debug;

/**
 * Class Translate
 * @package Libvaloa\I18n
 */
class Translate
{
    /**
     * @var
     */
    public $translated;

    /**
     * @var
     */
    public $domain;

    /**
     * @var array
     */
    public static $properties = array(
        'backend' => 'Ini',
    );

    /**
     * Translate constructor.
     * @param array $params
     */
    public function __construct($params = false)
    {
        if (!empty($params) && is_array($params)) {
            if (isset($params['backend'])) {
                self::$properties['backend'] = $params['backend'];
                unset($params['backend']);
            }
        }

        Debug::__print('Translator with backend ' .  self::$properties['backend']);

        if ($params === false || !is_array($params)) {
            $params = [];

            Debug::__print('Warning: Translator excepts parameters as an Array, parameters were discarded');
        }
        Debug::__print($params);

        $backend = '\Libvaloa\I18n\Translate\\'.self::$properties['backend'];

        $this->backend = new $backend($params);
    }

    /**
     * @param $domain
     * @param bool $path
     */
    public function bindTextDomain($domain, $path = false)
    {
        $this->backend->bindTextDomain($domain, $path);
    }

    /**
     * @return mixed
     */
    public function translate()
    {
        return $this->backend->translate();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->translate();
    }
}
