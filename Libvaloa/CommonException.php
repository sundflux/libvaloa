<?php

/**
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@amigaone.cc>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2006 Joni Halme <jontsa@amigaone.cc>
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

/**
 * Main exception class. All other libvaloa exceptions should extend this class.
 */

namespace Libvaloa;

class CommonException extends Exception
{
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return $this->message;
    }

    public function __call($m, $a)
    {
        foreach (debug_backtrace() as $tv) {
            if (isset($tv['function']) && $tv['function'] === $m) {
                break;
            }
        }

        trigger_error('Call to an undefined method '.
            get_class($this)."::{$m}() in {$tv['file']} line {$tv['line']}",
            E_USER_ERROR);

        exit;
    }
}
