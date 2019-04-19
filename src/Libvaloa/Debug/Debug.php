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
 * 2014, 2015 Tarmo Alexander Sundström <ta@sundstrom.io>
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
namespace Libvaloa\Debug;

use stdClass;

/**
 * Debugger class.
 *
 * Prints out debug messages and script benchmarks in non-obstructive manner.
 * The messages are printed out only if error level is set to E_ALL and
 * printing happens in template-agnostic way with register_shutdown_function.
 *
 * Include:
 * <code>
 * use Libvaloa\Debug\Debug;
 * </code>
 *
 * in your class/application and simply call:
 *
 * <code>
 * Debug::__print('Hello world');
 * </code>
 *
 * The debug message will print out at end of the page, together with
 * memory usage and execution time.
 */
class Debug
{
    private static $data = array();
    private static $shutdown = false;

    /**
     * Append debug messages to debug object.
     */
    public static function append()
    {
        if (error_reporting() !== E_ALL) {
            return;
        }

        $value = func_get_args();

        if (count($value) < 2) {
            $value = reset($value);
        }

        $debugobj = new stdClass();
        $debugobj->time = Timer::benchScript(5);
        $debugobj->mu = memory_get_usage();
        $debugobj->type = gettype($value);

        if (is_array($value) || is_object($value)) {
            $debugobj->data = @print_r($value, true);
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
            register_shutdown_function(array("Libvaloa\Debug\Debug", 'dump'));
            self::$shutdown = true;
        }

        self::$data[] = $debugobj;
    }

    /**
     * Dump debug messages at shutdown.
     */
    public static function dump()
    {
        if (error_reporting() !== E_ALL) {
            return;
        }

        print '<pre class="libvaloa--debug">';
        foreach (self::$data as $v) {
            echo sprintf(
                '<code><strong>%s</strong> <br/> Memory usage %s bytes<br/> %s&#160;[%s]&#160;%s</code><br/>',
                $v->backtrace,
                $v->mu,
                $v->time,
                $v->type,
                (in_array(
                    $v->type,
                    array('array', 'object'),
                    true
                ) ? '<code>'.$v->data.'</code>' : $v->data)
            );
        }
        print '</pre>';

        self::$data = array();
    }

    /**
     * Prints debug message with backtrace when E_ALL error level is set.
     */
    public static function __print()
    {
        if (error_reporting() !== E_ALL) {
            return;
        }

        // Prevent output when doing AJAX/JSON requests

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return;

            if (isset($_SERVER['HTTP_ACCEPT']) && in_array(
                'application/json',
                explode(',', $_SERVER['HTTP_ACCEPT']),
                true
            )) {
                return;
            }
        }

        $a = func_get_args();
        call_user_func_array(array("\Libvaloa\Debug\Debug", 'append'), $a);
    }
}
