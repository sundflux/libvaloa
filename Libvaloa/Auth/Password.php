<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2013 Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace Libvaloa\Auth;

class Password
{
    /**
     * Settings.
     *
     * If passwordUseCrypt is set to 1, uses PHP's crypt for password
     * hash (recommended if safe crypto extensions are enabled), otherwise
     * provides double rainbow hashing as fallback.
     *
     * @var array
     */
    public static $properties = array(
        'passwordUseCrypt' => 1,
    );

    /**
     * Hash password for safe storage.
     *
     * @param type $username
     * @param type $plaintextPassword
     *
     * @return string
     */
    public static function cryptPassword($username, $plaintextPassword)
    {
        $username = trim($username);
        $plaintextPassword = trim($plaintextPassword);

        if (self::$properties['passwordUseCrypt'] == 1) {
            return crypt($username.$plaintextPassword);
        }

        $password = str_split(
            $plaintextPassword,
            (strlen($plaintextPassword) / 2) + 1);

        return hash('sha1', $username.$password[0].'$$'.$password[1]);
    }

    /**
     * Verifies crypted password.
     *
     * @param type $username
     * @param type $plaintextPassword
     * @param type $crypted
     *
     * @return bool
     */
    public static function verify($username, $plaintextPassword, $crypted)
    {
        if (self::$properties['passwordUseCrypt'] == 1) {
            if (crypt($username.$plaintextPassword, $crypted) == $crypted) {
                return true;
            }

            return false;
        } else {
            if (self::cryptPassword($username, $plaintextPassword) == $crypted) {
                return true;
            }
        }

        return false;
    }
}
