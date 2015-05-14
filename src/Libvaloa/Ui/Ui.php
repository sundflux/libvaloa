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
namespace Libvaloa\Ui;

interface Ui
{
    /**
     * Construct ui with given page root name.
     *
     * @param string $root
     */
    public function __construct($root);

    /**
     * Add include path for UI resources.
     *
     * @param string $path
     */
    public function addIncludePath($path);

    /**
     * Return list of UI resource paths.
     *
     * @return array
     */
    public function getIncludePaths();

    /**
     * Get page root name (local template).
     *
     * @return string
     */
    public function getPageRoot();

    /**
     * Set page root name (local template).
     *
     * @param string $pageroot
     */
    public function setPageRoot($pageRoot);

    /**
     * Ignore template(s) with given name.
     *
     * @param string $file
     */
    public function ignoreTemplate($file);

    /**
     * Set main template file.
     *
     * @param string $file
     */
    public function setMainTemplate($file);

    /**
     * Add JavaScript resource.
     *
     * @param string $file
     */
    public function addJS($file);

    /**
     * Add CSS resource.
     *
     * @param string $file
     */
    public function addCSS($file);

    /**
     * Add template resource.
     *
     * @param string $file
     */
    public function addTemplate($file);

    /**
     * Add view data.
     *
     * @param object $object
     */
    public function addObject($object);

    /**
     * Add success message.
     *
     * @param string $message
     */
    public function addSuccess($message);

    /**
     * Add error message.
     *
     * @param string $message
     */
    public function addError($message);

    /**
     * Add notice message.
     *
     * @param string $message
     */
    public function addNotice($message);

    /**
     * Add message. Success, error and notice aliases use this.
     *
     * @param string $message
     * @param string $class
     */
    public function addMessage($message, $class);

    /**
     * Run preprocessing tasks for templates.
     */
    public function preProcessTemplate();

    /**
     * Return preprocessed template dom.
     *
     * @return DomDocument
     */
    public function getPreProcessedTemplateDom();

    /**
     * Set preprocessed template dom.
     *
     * @param DomDocument $v
     */
    public function setPreProcessedTemplateDom($v);

    /**
     * Add HTTP header.
     *
     * @param string $header
     */
    public function addHeader($header);

    /**
     * Return HTTP headers.
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Set all HTTP headers as array.
     *
     * @param array $headers
     */
    public function setHeaders($headers);

    /**
     * Parse the UI. Magic method __toString() should call this.
     *
     * @return mixed
     */
    public function parse($v);

    /**
     * Parse the UI.
     *
     * @return mixed
     */
    public function __toString();
}
