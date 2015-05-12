<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2004, 2015 Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace Libvaloa;

class Debugtimer
{
    private $startTime;
    private $startMem;

    /**
     * Constructor, starts benchmark timing.
     */
    public function __construct()
    {
        $this->startCounter();
    }

    /**
     * Starts counter by storing current microtime to $startTime variable.
     *
     * @access public
     *
     * @return float Current microtime
     */
    public function startCounter()
    {
        $this->startTime = microtime(true);
        $this->startMem = memory_get_usage();

        return $this->startTime;
    }

    /**
     * Returns current memory usage.
     *
     * @return mixed
     */
    public function memory()
    {
        return memory_get_usage() - $this->startMem;
    }

    /**
     * Stops counter and returns benchmark in seconds from the
     * time libvaloa environment was started.
     *
     * @access public
     *
     * @param int $decimals number of decimals in benchmark time
     *
     * @return float Benchmark time
     */
    public static function benchScript($decimals = 3)
    {
        return sprintf('%0.'.(int) $decimals.'f',
            (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]));
    }

    /**
     * Stops counter and returns benchmark in seconds.
     *
     * @access public
     *
     * @param int $decimals number of decimals in benchmark time
     *
     * @return float Benchmark time
     */
    public function stop($decimals = 3)
    {
        return sprintf('%0.'.(int) $decimals.'f',
            (microtime(true) - $this->startTime));
    }
}
