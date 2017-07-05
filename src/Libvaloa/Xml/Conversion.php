<?php

/**
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2010 Joni Halme <jontsa@angelinecms.info>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2010 Tarmo Alexander Sundström <ta@sundstrom.io>
 * 2014 Tarmo Alexander Sundström <ta@sundstrom.io>
 * 2017 Tarmo Alexander Sundström <ta@sundstrom.io>
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
 * Conversion, as the name suggests, converts XML-data between following formats:
 * - DOM
 * - SimpleXML
 * - String
 * - PHP object
 * - File.
 *
 * It can also apply stylesheets to any of the previous inputs and output the
 * parsed data in any of these formats.
 *
 * This class is not for manipulating XML data. You have PHP DOM and SimpleXML for that.
 * This is only to easily convert between between previous formats and apply stylesheets.
 *
 * The most common usage ofcourse is to convert PHP objects to DomDocuments and vice versa.
 * This conversion works pretty much like the one in Xml-class but this class also supports
 * - attributes
 * - cdata (properly unlike Xml-class)
 * - ArrayAccess classes
 *
 * Example. This loads XML data from file, edits it using SimpleXML, applies stylesheets
 * and echoes the results:
 *
 * $convert = new Xml_Conversion("/path/to/my.xml");
 * $sxml = $convert->toSimpleXML();
 * $sxml->users[0]->name = "John Doe";
 * $styles[0] = "/path/to/my.xsl";
 * $styles[1] = "/path/to/another.xsl";
 * $convert = new Xml_Conversion($sxml);
 * $convert->addStylesheet($styles);
 * echo $convert->toString();
 */
namespace Libvaloa\Xml;

use stdClass;
use DomDocument;
use DomAttr;
use DomNode;
use DomXPath;
use SimpleXMLElement;
use XsltProcessor;
use ArrayAccess;
use BadMethodCallException;
use InvalidArgumentException;

class Conversion
{
    private $source; // source data
    private $sourceType; // type of the source data as int
    private $styles = array(); // paths to xsl stylesheets
    
    const SOURCE_TYPE_DOM = 0;
    const SOURCE_TYPE_SIMPLEXML = 1;
    const SOURCE_TYPE_OBJECT = 2;
    const SOURCE_TYPE_FILE = 3;
    const SOURCE_TYPE_STRING = 4;

    /**
     * Constructor takes the source XML as parameter.
     * The source can be any of the supported formats:
     * DomDocument, SimpleXMLElement, PHP object, path to file or XML string.
     *
     * @param mixed $source Pointer to source
     *
     * @todo allow DomNodes alongside of DomDocument as parameter
     */
    public function __construct(&$source)
    {
        if ($source instanceof DomDocument) {
            $this->sourceType = self::SOURCE_TYPE_DOM;
        } elseif ($source instanceof SimpleXMLElement) {
            if (!class_exists('SimpleXMLElement')) {
                throw new BadMethodCallException('Can not parse XML from SimpleXMLElement. SimpleXML extension is missing.');
            }

            $this->sourceType = self::SOURCE_TYPE_SIMPLEXML;
        } elseif (is_object($source) || is_array($source)) {
            $this->sourceType = self::SOURCE_TYPE_OBJECT;

            // only the first element of array/object is used
            // because xml can have only one root element.
            // @todo remove other elements but first
        } elseif (is_file($source)) {
            $this->sourceType = self::SOURCE_TYPE_FILE;
        } elseif (is_string($source) && !empty($source)) {
            // @todo validation, charset selection
            $this->sourceType = self::SOURCE_TYPE_STRING;
        } else {
            throw new InvalidArgumentException('XML source is invalid.');
        }

        $this->source = $source;
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
        if (empty($node)
            || is_numeric(substr($node, 0, 1))
            || substr(strtolower($node), 0, 3) === 'xml'
            || strstr($node, ' ')) {
            return false;
        }

        return true;
    }

    /**
     * @todo should we catch exceptions and return error string?
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Converts source to XML string.
     *
     * @param bool $applystyles Apply stylesheets yes/no. Default is true.
     *
     * @return string
     *
     * @todo support for passing XsltProcessor as parameter
     * @todo support for DomNode as source when passing data to XsltProcessor
     */
    public function toString($applystyles = true)
    {
        // create XsltProcessor if needed
        if ($applystyles && !empty($this->styles)) {
            $proc = self::stylesToProc($this->styles);
            $proc->registerPhpFunctions();
        }

        // source parsing if needed
        switch ($this->sourceType) {
            case self::SOURCE_TYPE_DOM:
                $dom = $this->source;
                break;
            case self::SOURCE_TYPE_SIMPLEXML:
                if (!isset($proc)) {
                    return $this->source->asXML();
                }
            default:
                $dom = $this->toDOM(false);
        }

        // if no stylesheets were selected, just return XML string
        if (!isset($proc)) {
            if (!$dom instanceof DomDocument) {
                // when parameter for constructor was DomNode and not domdocument
                return $dom->ownerDocument->saveXML($dom);
            }

            return $dom->saveXML();
        }

        // apply stylesheets and return parsed data as string.
        return (string) $proc->transformToXML($dom);
    }

    /**
     * Converts source to DomDocument.
     *
     * @param bool $applystyles Apply stylesheets yes/no. Default is true.
     *
     * @return DomDocument
     *
     * @todo support for passing XsltProcessor as parameter
     */
    public function toDOM($applystyles = true)
    {
        if ($applystyles && !empty($this->styles)) {
            $proc = self::stylesToProc($this->styles);
            $proc->registerPhpFunctions();
        }

        switch ($this->sourceType) {
            case self::SOURCE_TYPE_DOM:
                if ($this->source instanceof DomDocument) {
                    return $this->source;
                }

                // when parameter for constructor was DomNode
                $dom = new DomDocument('1.0', 'utf-8');
                $dome = $dom->importNode($this->source, true);
                $dom->appendChild($dome);

                return $dom;
            case self::SOURCE_TYPE_SIMPLEXML:
                // @todo detect charset from simplexml?
                $dom = new DomDocument('1.0', 'utf-8');
                $dome = dom_import_simplexml($this->source);
                $dome = $dom->importNode($dome, true);
                $dom->appendChild($dome);

                return $dom;
            case self::SOURCE_TYPE_OBJECT:
                $dom = $this->objectToDom($this->source);
                break;
            case self::SOURCE_TYPE_FILE:
                $dom = new DomDocument();
                $dom->load($this->source);
                break;
            case self::SOURCE_TYPE_STRING:
                $dom = new DomDocument();
                $dom->loadXML($this->source);
        }

        return isset($proc) ? $proc->transformToDoc($dom) : $dom;
    }

    /**
     * Converts source to SimpleXMLElement.
     *
     * @param bool $applystyles Apply stylesheets yes/no. Default is true.
     *
     * @return SimpleXMLElement
     *
     * @todo support for passing XsltProcessor as parameter
     * @todo check simplexml_load_file() return value for false
     * @todo simplexml_check load_string() return value for false
     */
    public function toSimpleXML($applystyles = true)
    {
        if ($applystyles && !empty($this->styles)) {
            $dom = $this->toDOM($applystyles);

            return simplexml_import_dom($dom);
        }

        switch ($this->sourceType) {
            case self::SOURCE_TYPE_DOM:
                return simplexml_import_dom($this->source);
            case self::SOURCE_TYPE_SIMPLEXML:
                return $this->source;
            case self::SOURCE_TYPE_OBJECT:
                $dom = $this->objectToDom($this->source);

                return simplexml_import_dom($dom);
            case self::SOURCE_TYPE_FILE:
                return simplexml_load_file($this->source);
            case self::SOURCE_TYPE_STRING:
                return simplexml_load_string($this->source);
        }
    }

    /**
     * Converts source to PHP object.
     *
     * @param bool $applystyles Apply stylesheets yes/no. Default is true.
     *
     * @return object
     *
     * @todo support for passing XsltProcessor as parameter
     * @todo alternate method for converting simplexml to object?
     */
    public function toObject($applystyles = true)
    {
        if ($this->sourceType === 2 && (!$applystyles || empty($this->styles))) {
            return $this->source;
        }

        $dom = $this->toDOM($applystyles);

        return $this->domToObject($dom);
    }

    /**
     * Converts source to XML string and write it to file.
     *
     * @param mixed $filename    Optional filename to write to, default is to create temporary file
     * @param bool  $applystyles Apply stylesheets yes/no. Default is true.
     *
     * @return string Filename
     *
     * @todo support for passing XsltProcessor as parameter
     * @todo Filewriting, creating temporary files
     */
    public function toFile($filename = false, $applystyles = true)
    {
        return $filename;
    }

    /**
     * Add stylesheet(s) to converter.
     *
     * @param mixed $files Either single file path as string or array of files
     *
     * @todo support for stylesheets in string
     */
    public function addStylesheet($files)
    {
        $files = array_filter((array) $files);

        foreach ($files as $v) {
            if ($v instanceof DomDocument) {
                $this->styles[] = $v;
            } elseif (!is_string($v) || in_array($v, $this->styles, true)) {
                return;
            } elseif (is_string($v)) {
                $this->styles[] = $v;
            }
        }
    }

    /**
     * Clears stylesheets from converter.
     * Note that you can just pass $applystyles=false parameter to converter to
     * disable stylesheets from output.
     */
    public function clearStylesheets()
    {
        $this->styles = array();
    }

    /**
     * Converts XSL files to XsltProcessor instance.
     *
     * @param mixed $files Either single file path as string or array of files
     *
     * @return XsltProcessor
     */
    public static function stylesToProc($files = array())
    {
        if (!class_exists('XsltProcessor')) {
            throw new BadMethodCallException('XSL extension is missing. Can not create XsltProcessor.');
        }

        $dom = self::stylesToDOM($files);
        $proc = new XsltProcessor();
        $proc->importStylesheet($dom);

        return $proc;
    }

    /**
     * Converts XSL files to DomDocument.
     *
     * @param mixed $files Either single file path as string or array of files, files can also be DomDocuments
     *
     * @return DomDocument
     */
    public static function stylesToDOM($files = array())
    {
        foreach ($files as $primary => &$v) {
            if (!$v instanceof DomDocument) {
                $dom = new DomDocument();
                $dom->load($v);
            } else {
                $dom = $v;
            }

            if ($dom->firstChild->nodeName === 'xsl:stylesheet') {
                break;
            }

            unset($primary);
        }

        if (!isset($primary)) {
            throw new RuntimeException('No valid XML stylesheets were found for XSLT parser.');
        }

        foreach ($files as $k => &$v) {
            if ($k === $primary) {
                continue;
            }

            if ($v instanceof DomDocument) {
                if ($v->firstChild->nodeName !== 'xsl:stylesheet') {
                    continue;
                }

                foreach ($v->firstChild->childNodes as $include) {
                    $dom->appendChild($dom->importNode($include, true));
                }
            } else {
                $include = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:include');
                $include->setAttributeNode(new DomAttr('href', $v));
                $dom->firstChild->appendChild($include);
            }
        }

        return $dom;
    }

    /**
     * Converts PHP object to DomDocument.
     * Note that only the first element in object is converted as XML
     * can only have one root element.
     *
     * @param mixed $obj;
     *
     * @return DomDocument
     */
    public function objectToDom($obj)
    {
        $doc = new DomDocument('1.0', 'utf-8');

        foreach ($obj as $k => &$v) {
            $node = $this->objectToDomElement($v, $doc->createElement($k), $doc);
            break;
        }

        $doc->appendChild($node);

        return $doc;
    }

    /**
     * Recursive object to DomElement converter.
     *
     * @param stdClass    $obj
     * @param DomNode     $node
     * @param DomDocument $doc  Root document
     */
    public function objectToDomElement($obj, DomNode $node, DomDocument $doc)
    {
        if (isset($obj->__cdata)) {
            $node->appendChild($doc->createCDATASection($obj->__cdata));
        }

        foreach ($obj as $key => &$val) {
            if (in_array($key, array('__attr', '__cdata'), true)) {
                continue;
            } elseif (is_array($val) || $val instanceof ArrayAccess) {
                foreach ($val as $k => &$v) {
                    $e = $doc->createElement($key);

                    if (is_object($v)) {
                        $this->objectToDomElement($v, $e, $doc);
                    } elseif ($v !== null) {
                        $e->appendChild($doc->createTextNode((string) $v));
                    }

                    if (isset($obj->__attr) && isset($obj->__attr[$key][$k])) {
                        foreach ((array) $obj->__attr[$key][$k] as $k2 => $v2) {
                            $e->setAttribute($k2, (string) $v2);
                        }
                    }

                    $node->appendChild($e);
                }
                continue;
            } elseif (is_object($val)) {
                $e = $this->objectToDomElement($val, $doc->createElement($key), $doc);
            } elseif ($val !== null) {
                $e = $doc->createElement($key);
                $e->appendChild($doc->createTextNode($val));
            } else {
                continue;
            }

            if (isset($obj->__attr) && isset($obj->__attr[$key])) {
                foreach ((array) $obj->__attr[$key] as $k => $v) {
                    $e->setAttribute($k, (string) $v);
                }
            }

            $node->appendChild($e);
        }

        return $node;
    }

    public function domToObject($dom)
    {
        return $this->processNode('/*', new DomXPath($dom));
    }

    /**
     * @todo support for cdata and attributes
     * @todo more testing and optimize?
     * @todo support for simplexml
     */
    private function processNode($path, $xpath = false)
    {
        $items = $xpath->query("{$path}");

        if (!is_object($items)) {
            return false;
        }

        if ($items->length > 1) {
            $retval = array();

            foreach ($items as $k => $item) {
                array_push($retval, $this->processNode("{$path}[".($k + 1).']', $xpath));
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
                        array_push($retval->{$item->nodeName}, $this->processNode("{$path}/{$item->nodeName}[{$count}]", $xpath));
                    } else {
                        $retval->{$item->nodeName} = $this->processNode("{$path}/{$item->nodeName}[1]", $xpath);
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
}
