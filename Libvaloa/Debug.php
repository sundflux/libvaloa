<?php

/**
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@amigaone.cc>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2009 Joni Halme <jontsa@amigaone.cc>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace Libvaloa;

use stdClass;
use Webvaloa\Controller\Request;

class Debug
{
    private static $data = array();
    private static $shutdown = false;

    /**
     * Append debug messages to debug object.
     */
    public static function append()
    {
        $value = func_get_args();

        if (count($value) < 2) {
            $value = reset($value);
        }

        $debugobj = new stdClass();
        $debugobj->time = Benchmark::benchScript(5);
        $debugobj->mu = memory_get_usage();
        $debugobj->type = gettype($value);

        if (is_array($value) || is_object($value)) {
            $debugobj->data = @ print_r($value, true);
        } else {
            $debugobj->data = $value;
        }

        $backtrace = debug_backtrace();

        $calledFrom = '';
        if (isset($backtrace[3]['function'])) {
            $calledFrom = $backtrace[3]['function'];
        }

        $debugobj->backtrace = "{$backtrace[2]['file']} line {$backtrace[2]['line']} (Called from {$calledFrom}())";

        if (self::$shutdown === false) {
            register_shutdown_function(array("Libvaloa\Debug", 'dump'));
            self::$shutdown = true;
        }

        self::$data[] = $debugobj;
    }

    /**
     * Dump debug messages at shutdown.
     */
    public static function dump()
    {
        if (class_exists('\\Libvaloa\\Db\\Db')) {
            self::__print('Executed '.\Libvaloa\Db\Db::$querycount.' sql queries');
        }

        self::__print('Libvaloa finished');

        print '<pre id="debug">';
        foreach (self::$data as $v) {
            echo sprintf('<code><strong>%s</strong> <br/> Memory usage %s bytes<br/> %s&#160;[%s]&#160;%s</code><br/>',
                $v->backtrace,
                $v->mu,
                $v->time,
                $v->type,
                (in_array(
                    $v->type,
                    array('array', 'object'), true) ? '<code>'.$v->data.'</code>' : $v->data));
        }
        print '</pre>';

        self::$data = array();
    }

    /**
     * Prints debug message with backtrace when E_ALL error level is set.
     *
     * @return type
     */
    public static function __print()
    {
        if (error_reporting() != E_ALL || Request::getInstance()->isJson() || Request::getInstance()->isAjax()) {
            return;
        }

        $a = func_get_args();
        call_user_func_array(array("\Libvaloa\Debug", 'append'), $a);
    }
}
