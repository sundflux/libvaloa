<?php
/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2004,2013,2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006,2010 Joni Halme <jontsa@amigaone.cc>
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
 * Creates XHTML user interface using Xml_Xsl.
 *
 * @package    Kernel
 * @subpackage Xml
 * @uses       Xml_Xsl
 */

namespace Libvaloa\Ui;

use stdClass;
use Libvaloa\Debug;
use Libvaloa\Xml\Xsl as Xsl;

class Xml extends \Libvaloa\Xml\Xml Implements Ui
{

    public $issetpageroot = false;

    /**
     * @access private
     * @var DOMElement 'pageroot' which can be anything. Default is 'index'
     */
    private $page;

    private $requisites;
    private $mainxsl;
    private $xsl;
    private $asxml = false;
    private $paths = array();

    private $ignoreTemplates = array();

    public $properties = array(
        'controller' => '',
        'parentController' => '',
        'route' => '',
        'basehref' => '',
        'basepath' => '',
        'locale' => '',
        'title' => '',
        'layout' => '',
        'userid' => '',
        'user' => '',
        'contenttype' => 'text/html',
        'useCache' => 0,
        'override_template' => false,
        'override_layout' => false
    );

    /**
     * Constructor.
     *
     * @access public
     * @param mixed $root XML page root. Default is 'page' and you
     *                    shouldn't change this
     */
    public function __construct($root = 'page')
    {
        parent::__construct($root);

        $this->xsl = new Xsl;
        $this->xsl->properties['useCache'] = $this->properties['useCache'];
        $this->page = $this->dom->createElement('index');
        $this->requisites = new stdClass;
    }

    public function includePath($paths)
    {
        $paths = (array) $paths;

        foreach ($paths as &$path) {
            if (!in_array($path, $this->paths, true)) {
                $this->paths[] = $path;
            }
        }
    }

    public function getIncludePaths()
    {
        return $this->paths;
    }

    /**
     * Swap between xhtml and xml output.
     * @param bool $val
     */
    public function asXML($val)
    {
        $this->asxml = (bool) $val;
    }

    /**
     * Sets page root node name under /page/module.
     *
     * Tip: You can gain a minimal speed increase if you set your page
     *      root before adding XML.
     *
     * @access public
     * @param  string $root Pageroot node name
     * @return bool
     */
    public function setPageRoot($root = false)
    {
        $this->issetpageroot = true;

        if ($this->validateNodeName($root) && $root != $this->page->nodeName) {
            $nodelist = $this->page->childNodes;
            $this->page = $this->dom->createElement($root);

            foreach ($nodelist as $item) {
                $this->page->appendChild($item->cloneNode(true));
            }
        }
    }

    /**
    * Returns if page root isset
    * @return mixed
    */
    public function issetPageRoot()
    {
        return $this->issetpageroot;
    }

    /**
     * Adds an object to XML tree.
     *
     * $object parameter can also be an array but array keys must be strings.
     *
     * @todo    Validate $object
     * @access  public
     * @param object  $object XMLVars object
     * @param boolean $cdata  Defines if we create CDATA section to XMLtree
     * @param mixed   $key    Optional nodename to put XML data under. If false,
     *                        data is put to page root, otherwise to XML-root.
     */
    public function addObject($object, $cdata = false, $key = false)
    {
        if (!is_object($object) && !is_array($object)) {
            return;
        }

        if ($key && $this->validateNodeName($key)) {
            $key = $this->dom->createElement($key);
            $this->objectToNode($object, $key, $cdata);
            $this->dom->firstChild->appendChild($key);
        } else {
            $this->objectToNode($object, $this->page, $cdata);
        }
    }

    /**
     * Add template to ignore list.
     */
    public function ignoreTemplate($file)
    {
        $this->ignoreTemplates[] = $file;
    }

    /**
     * Set Main XSL file to load.
     *
     * $param string $file
     */
    public function setMainTemplate($file)
    {
        $this->mainxsl = $file;
    }

    /**
     * Adds common XML data and returns XSL parser output.
     *
     * @param bool $asxml If true, return value will be XML
     * @access public
     * @uses   Xml_Read
     */
    public function parse($asxml = false)
    {
        $xml = new stdClass;

        // Error messages to XML
        if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])) {
            $xml->messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
        }

        // Page requisites
        foreach ($this->requisites as $k => $v) {
            if (is_array($v)) {
                $v = array_unique($v);
            }

            $xml->{$k} = $v;
        }

        // Properties
        foreach ($this->properties as $k => $v) {
            if (!empty($v)) {
                $xml->$k = $v;
            }
        }

        $this->addObject($xml, false, 'common');

        // Return parsed XHTML
        $module = $this->dom->createElement('module');
        $module->appendChild($this->page);
        $this->dom->firstChild->appendChild($module);

        try {
            $retval = $this->asxml ? Xml::toString() : $this->xsl->parse($this->dom);
        } catch (Exception $e) {
            Debug::__print($e->getMessage());
            $retval = false;
        }

        return $retval;
    }

    /**
     * Detects path and file of 'main' XSL file.
     *
     * 'Main' XSL means the root XSL template. If/when found, it is added to list of
     * XSL files to include.
     *
     * @access public
     * @return bool
     */
    public function detectMainTemplate()
    {
        $name = (isset($this->mainxsl) && !empty($this->mainxsl)) ? $this->mainxsl : 'index';

        foreach ($this->getIncludePaths() as $path) {
            if (is_readable($path.DIRECTORY_SEPARATOR.$name.'.xsl')) {
                $this->xsl->includeXSL($path.DIRECTORY_SEPARATOR.$name.'.xsl', true);

                return $path.DIRECTORY_SEPARATOR.$name.'.xsl';
            }
        }
    }

    /**
     * Add javascript to be included in XHTML.
     *
     * @access public
     * @param string $file   Javascript file
     * @param mixed  $module False to search JS-file from current module and
     *                       theme directories, true to search just module directory or name
     *                       of the module
     */
    public function addJS($name = false)
    {
        $this->addFile($name);
    }

    /**
     * Add CSS file to be included in XHTML.
     *
     * File is first looked from theme directory and then from default css directory.
     *
     * @access public
     * @param string $name   CSS filename
     * @param mixed  $module False to search CSS-file from current module and
     *                       theme directories, true to search just module directory or
     *                       name of the module
     */
    public function addCSS($name = false)
    {
        $this->addFile($name);
    }

    private function addFile($name = false)
    {
        $ext = substr(strrchr($name, '.'), 1);

        foreach ($this->paths as $path) {
            if (is_readable($path.$name)) {
                $this->requisites->{$ext}[] = $name;

                return;
            }
        }
    }

    /**
     * Add XSL-file to parser.
     *
     * File is first looked from extensionspath/modules directory, then module
     * directory. If module name was not specified or $module was set to true,
     * method also looks up the file in theme directories.
     *
     * @access public
     * @param  string $name   XSL-filename without .xsl suffix
     * @param  mixed  $module False to search XSL-file from current module and
     *                        theme directories, true to search just module directory or
     *                        name of the module
     * @return bool   True if XSL file was included, otherwise false
     */
    public function addTemplate($name = false)
    {
        // The template is on ignore list, don't add it
        if (in_array($name, $this->ignoreTemplates)) {
            return false;
        }

        $name.= '.xsl';

        foreach ($this->paths as $path) {
            if (is_readable($path.DIRECTORY_SEPARATOR.$name)) {
                $this->xsl->includeXSL($path.DIRECTORY_SEPARATOR.$name);

                return;
            }
        }
    }

    /**
     * Add a error to session.
     *
     * @access public
     * @param mixed $message Error message or array of errors
     */
    public function addError($message)
    {
        $this->addMessage($message, 'error');
    }

    /**
     * Add a message to session.
     *
     * @access public
     * @param mixed  $message Message or array of message strings
     * @param string $class   Tells XSL/CSS which type of message this is
     */
    public function addMessage($message, $class = 'message')
    {
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }

        if (is_object($message)) {
            $message = (string) $message;
        }

        $message = (array) $message;
        foreach ($message as &$v) {
            $msgObj = new stdClass;
            $msgObj->item = (string) $v;
            $msgObj->type = $class;
            $_SESSION['messages'][] = $msgObj;
        }
    }

    public function preProcessTemplate()
    {
        $this->detectMainTemplate();

        return $this->xsl->preProcessTemplate();
    }

    public function getPreProcessedTemplateDom()
    {
        return $this->xsl->getPreProcessedTemplateDom();
    }

    public function setPreProcessedTemplateDom($v)
    {
        return $this->xsl->setPreProcessedTemplateDom($v);
    }

    /**
     * self to string conversion.
     *
     * @access public
     * @return string XHTML output
     */
    public function __toString()
    {
        try {
            return (string) $this->parse();
        } catch (Exception $e) {
            return "";
        }
    }

}
