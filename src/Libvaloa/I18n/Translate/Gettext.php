<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2018 Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace Libvaloa\I18n\Translate;

use Libvaloa\Debug;

/**
 * Class Gettext
 * @package Libvaloa\I18n\Translate
 */
class Gettext
{
    /**
     * @var
     */
    private $source;

    /**
     * @var
     */
    private $translated;

    /**
     * @var bool
     */
    private $translations;

    /**
     * Ini constructor.
     * @param $params
     */
    public function __construct($params)
    {
        $this->translations = false;
        $this->translated = $this->source = $params[0];
    }

    /**
     * @param $domain
     * @param string $path
     */
    public function bindTextDomain($domain, $path = '')
    {
        $file = $path.'/'.getenv('LANG').'/LC_MESSAGES/'.$domain.'.po';

        if (!file_exists($file)) {
            return false;
        }

        try {
            $translations = \Gettext\Translations::fromPoFile($file);
            $translation = $translations->find(null, $this->source);

            if ($translation === false) {
                throw new \RuntimeException('Translation not found');
            }

            $this->translations[$this->source] = $translation->getTranslation();
        } catch(\Exception $e) {
            Debug::__print('Loading translation failed.');
            Debug::__print($e->getMessage());
        }
    }

    /**
     * @return mixed
     */
    public function translate()
    {
        Debug::__print($this->translations[$this->source]);

        if (!empty($this->translations[$this->source])) {
            return $this->translations[$this->source];
        }

        return $this->source;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->translate();
    }
}
