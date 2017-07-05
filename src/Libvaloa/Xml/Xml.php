<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2004, 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2005,2006,2007 Joni Halme <jontsa@amigaone.cc>
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
 * XML parser.
 *
 * Creates XML data from PHP objects. Objects can contain
 * more objects, arrays, strings etc.
 *
 * @todo       Attribute support
 */
namespace Libvaloa\Xml;

use stdClass;
use DomDocument;
use DOMXPath;
use ArrayAccess;
use InvalidArgumentException;

class Xml
{
    /**
     * Instance of DomDocument.
     *
     *
     * @var DomDocument
     */
    protected $dom;

    private $paths = array();

    /**
     * Constructor.
     *
     * Fires up DomDocument.
     *
     * @todo   Add support for multiple encodings
     *
     * @param string $from XML root element name or DomDocument object with XML-data
     */
    public function __construct($from = false)
    {
        if ($from instanceof DomDocument) {
            $this->dom = $from;
        } else {
            if (!$from || !$this->validateNodeName($from)) {
                $from = 'root';
            }

            $this->dom = new DomDocument('1.0', 'utf-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->resolveExternals = false;

            // Format output when in debug mode
            if (error_reporting() === E_ALL) {
                $this->dom->formatOutput = true;
            } else {
                $this->dom->formatOutput = false;
            }

            $this->dom->appendChild($this->dom->createElement($from));
        }
    }

    /**
     * Checks if strings is valid as XML node name.
     *
     *
     * @param string $node Node name
     *
     * @return bool True if string can be used as node name, otherwise false.
     */
    public static function validateNodeName($node)
    {
        return Conversion::validateNodeName($node);
    }

    /**
     * Returns XML data as string.
     *
     *
     * @return string
     */
    public function toString()
    {
        return (string) $this->dom->saveXML();
    }

    public function toObject($path = '/*')
    {
        return $this->nodeToObject($path);
    }

    public function toDom($clone = true)
    {
        return $clone ? $this->dom->cloneNode(true) : $this->dom;
    }

    /**
     * Adds an object to XML tree.
     *
     * $object parameter can also be an array but array keys must be strings.
     *
     *
     * @param object $object XMLVars object or array
     * @param bool   $cdata  Defines if we create CDATA section to XMLtree
     */
    public function addObject($object, $cdata = false, $path = false)
    {
        if (is_object($object) || is_array($object)) {
            $root = $this->dom->firstChild;
            if ($path) {
                $xp = new DOMXPath($this->dom);
                $items = $xp->query($path);
                if ($items && $items->length > 0) {
                    $root = $items->item(0);
                }
            }

            if ($object instanceof DomDocument) {
                $object = new self($object);
            }

            if ($object instanceof \Libvaloa\Xml\Xml) {
                $object = $object->toObject();
            }

            $this->objectToNode($object, $root, $cdata);
        }
    }

    /**
     * Adds XML from file.
     *
     * @param string $file Target file
     * @param mixed  $path False or xpath string which will be returned as object from file.
     *
     * @return mixed
     */
    public static function fromFile($file, $path = false)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException('Source file for XML data was not found.');
        }

        $xml = new DomDocument();
        $xml->load($file);
        $xml = new self($xml);

        return $path ? $xml->toObject($path) : $xml;
    }

    /**
     * Adds XML from string.
     *
     * @param string $string XML as string
     * @param mixed  $path   False or xpath string which will be returned as object from XML string.
     *
     * @return mixed
     */
    public static function fromString($string, $path = false)
    {
        $xml = new DomDocument();
        $xml->loadXML($string);
        $xml = new self($xml);

        return $path ? $xml->toObject($path) : $xml;
    }

    /**
     * Parses object or array and converts it to XML.
     *
     * @todo   Validate $key (would it create too much overhead?)
     *
     * @param object $obj   Object to parse. In theory arrays will work if keys are not integers.
     * @param object $top   Toplevel DOM object
     * @param bool   $cdata Creates CDATASection if true
     */
    protected function objectToNode($obj, $top, $cdata)
    {
        foreach ($obj as $key => $val) {
            if (is_array($val) || is_object($val) && $val instanceof ArrayAccess) {
                foreach ($val as $v) {
                    $item = $this->dom->createElement($key);
                    if (is_object($v)) {
                        $this->objectToNode($v, $item, $cdata);
                    } elseif ($cdata) {
                        $item->appendChild($this->dom->createCDATASection($v));
                    } elseif (is_string($v) || is_int($v)) {
                        $item->appendChild($this->dom->createTextNode($v));
                    }
                    $top->appendChild($item);
                }
            } elseif (is_object($val)) {
                if (is_numeric($key)) {
                    $item = $this->dom->createElement($top->nodeName);
                } else {
                    $item = $this->dom->createElement($key);
                }
                $this->objectToNode($val, $item, $cdata);
                $top->appendChild($item);
            } else {
                $item = $this->dom->createElement($key);
                if ($cdata) {
                    $item->appendChild($this->dom->createCDATASection($val));
                } else {
                    $item->appendChild($this->dom->createTextNode($val));
                }
                $top->appendChild($item);
            }
        }
    }

    /**
     * Parses XML and converts it to object.
     *
     *
     * @param string   $path  XPath to XML element to return as object
     * @param DomXPath $xpath DomXPath object from current DomDocument
     *
     * @return mixed Either array, stdClass or false on error
     */
    protected function nodeToObject($path, $xpath = false)
    {
        if (!$xpath instanceof DOMXPath) {
            $xpath = new DOMXPath($this->dom);
        }
        $items = $xpath->query("{$path}");

        if (!is_object($items)) {
            return false;
        }

        if ($items->length > 1) {
            $retval = array();
            foreach ($items as $k => $item) {
                array_push($retval, $this->nodeToObject("{$path}[".($k + 1).']', $xpath));
            }
        } else {
            $retval = new stdClass();
            $nodelist = $xpath->query("{$path}/*");

            foreach ($nodelist as $item) {
                if (isset($retval->{$item->nodeName}) && is_object($retval->{$item->nodeName})) {
                    $retval->{$item->nodeName} = array(clone $retval->{$item->nodeName});
                }

                $tmp = $xpath->query("{$path}/{$item->nodeName}/*");
                if ($tmp->length > 0) {
                    if (isset($retval->{$item->nodeName})) {
                        $count = count($retval->{$item->nodeName}) + 1;
                        array_push($retval->{$item->nodeName}, $this->nodeToObject("{$path}/{$item->nodeName}[{$count}]", $xpath));
                    } else {
                        $retval->{$item->nodeName} = $this->nodeToObject("{$path}/{$item->nodeName}[1]", $xpath);
                    }
                } else {
                    if (isset($retval->{$item->nodeName})) {
                        if (is_array($retval->{$item->nodeName})) {
                            array_push($retval->{$item->nodeName}, $item->nodeValue);
                        } else {
                            if (isset($tmpval)) {
                                $retval->{$item->nodeName} = array($tmpval);
                                unset($tmpval);
                            }
                            array_push($retval->{$item->nodeName}, $item->nodeValue);
                        }
                    } else {
                        $retval->{$item->nodeName} = $item->nodeValue;
                        $tmpval = $item->nodeValue;
                    }
                }
            }
        }

        return $retval;
    }

    /**
     * self to string conversion.
     *
     *
     * @return string XML data as string
     */
    public function __toString()
    {
        try {
            return (string) $this->toString();
        } catch (Exception $e) {
            return '';
        }
    }
}
