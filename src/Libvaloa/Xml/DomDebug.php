<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.io>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.io>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
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
 * Based on excellent dom debugging techniques discussed here:
 * http://stackoverflow.com/questions/684227/debug-a-domdocument-object-in-php.
 */
namespace Libvaloa\Xml;

use Libvaloa\Debug;
use RecursiveIterator;
use RecursiveTreeIterator;
use DOMNode;
use DOMNodeList;
use ArrayIterator;
use IteratorIterator;

interface OuterIterator
{
    public function getInnerIterator();
    public function rewind();
    public function valid();
    public function current();
    public function key();
    public function next();
}

abstract class IteratorDecoratorStub implements OuterIterator
{
    private $iterator;

    public function __construct($iterator)
    {
        $this->iterator = $iterator;
    }

    public function getInnerIterator()
    {
        return $this->iterator;
    }

    public function rewind()
    {
        $this->iterator->rewind();
    }

    public function valid()
    {
        return $this->iterator->valid();
    }

    public function current()
    {
        return $this->iterator->current();
    }

    public function key()
    {
        return $this->iterator->key();
    }

    public function next()
    {
        $this->iterator->next();
    }
}

abstract class RecursiveIteratorDecoratorStub extends IteratorDecoratorStub implements RecursiveIterator
{
    public function __construct(RecursiveIterator $iterator)
    {
        parent::__construct($iterator);
    }

    public function hasChildren()
    {
        return $this->getInnerIterator()->hasChildren();
    }

    public function getChildren()
    {
        return new static($this->getInnerIterator()->getChildren());
    }
}

class DOMRecursiveDecoratorStringAsCurrent extends RecursiveIteratorDecoratorStub
{
    public function current()
    {
        $node = parent::current();
        $nodeType = $node->nodeType;

        switch ($nodeType) {
            case XML_ELEMENT_NODE:
                return $node->tagName;

            case XML_TEXT_NODE:
                return $node->nodeValue;

            default:
                return sprintf('(%d) %s', $nodeType, $node->nodeValue);
        }
    }
}

class DOMIterator extends IteratorDecoratorStub
{
    public function __construct($nodeOrNodes)
    {
        if ($nodeOrNodes instanceof DOMNode) {
            $nodeOrNodes = array($nodeOrNodes);
        } elseif ($nodeOrNodes instanceof DOMNodeList) {
            $nodeOrNodes = new IteratorIterator($nodeOrNodes);
        }
        if (is_array($nodeOrNodes)) {
            $nodeOrNodes = new ArrayIterator($nodeOrNodes);
        }

        parent::__construct($nodeOrNodes);
    }
}

class DOMRecursiveIterator extends \Libvaloa\Xml\DOMIterator implements RecursiveIterator
{
    public function hasChildren()
    {
        return $this->current()->hasChildNodes();
    }

    public function getChildren()
    {
        $children = $this->current()->childNodes;

        return new self($children);
    }
}

class DomDebug
{
    public function __printNode(\DOMNode $node)
    {
        // Needs debugs enabled
        if (error_reporting() != E_ALL) {
            return false;
        }

        $iterator = new DOMRecursiveIterator($node);
        $decorated = new DOMRecursiveDecoratorStringAsCurrent($iterator);
        $tree = new RecursiveTreeIterator($decorated);

        $output = array();
        foreach ($tree as $v) {
            $v = trim(str_replace("\n", '', $v));

            $checkEmpty = str_replace('|', '', $v);
            $checkEmpty = str_replace('-', '', $checkEmpty);
            $checkEmpty = str_replace(' ', '', $checkEmpty);

            if (!empty($v) && !empty($checkEmpty)) {
                $output[] = $v.'>';
            }
        }

        Debug::__print($output);
    }
}
