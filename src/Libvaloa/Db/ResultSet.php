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
 * 2008,2013,2018 Tarmo Alexander Sundström <ta@sundstrom.io>
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
namespace Libvaloa\Db;

use PDO;
use PDOStatement;
use Iterator;
use LogicException;
use InvalidArgumentException;
use Libvaloa\Debug;


/**
 * Class ResultSet
 * @package Libvaloa\Db
 */
class ResultSet implements Iterator
{
    /**
     * Array of rows from executed SQL query.
     *
     * @var array
     */
    private $rows = array();

    private $recordCount = 0;

    /**
     * Current row in resultset.
     *
     * @var int
     */
    private $index = 0;

    /**
     * PDOStatement if resultset was created with DB::prepare().
     *
     * @param  mixed
     */
    private $stmt = false;

    /**
     * Binding column count.
     *
     * @param  int
     */
    private $column = 1;

    /**
     * Constructor.
     *
     *
     * @param PDOStatement $stmt     Statement from DB
     * @param bool         $executed If true, rows are read automatically
     *                               from Statement instead of waiting for execute()
     */
    public function __construct($stmt, $executed = false)
    {
        if ($stmt instanceof PDOStatement) {
            if ($executed) {
                // This should fix problems with MySQL. PHP bug #42322
                if ($stmt->columnCount()) {
                    $this->rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                    $this->recordCount = count($this->rows);
                }
            } else {
                $this->stmt = $stmt;
            }
        } else {
            throw new InvalidArgumentException("Can't create SQL resultset. Invalid parameters.");
        }
    }

    /**
     * Member get overload method.
     *
     * Currently supports fields array and EOF boolean.
     *
     *
     * @return mixed
     */
    public function __get($k)
    {
        switch ($k) {
            case 'fields':
                if (isset($this->fields)) {
                    return $this->fields;
                } elseif (isset($this->rows[$this->index])) {
                    $this->fields = array();
                    foreach ($this->rows[$this->index] as $k => $v) {
                        $this->fields[$k] = $v;
                    }

                    return $this->fields;
                }

                return array();
            case 'EOF':
                return ($this->index >= $this->recordCount);
        }
    }

    /**
     * @param $value
     * @param bool $key
     * @param PDO::PARAM $type
     * @return $this
     */
    public function set($value, $key = false, $type = PDO::PARAM_STR)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindValue($key, $value, $type);

            return $this;
        }

        throw new LogicException('Program attempted to set parameter to an executed SQL query.');
    }

    /**
     * @param $value
     * @param bool $key
     * @param PDO::PARAM $type
     * @return $this
     */
    public function bind(&$value, $key = false, $type = PDO::PARAM_STR)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindParam($key, $value, $type);

            return $this;
        }

        throw new LogicException('Program attempted to bind parameter to an executed SQL query.');
    }

    /**
     * Executes a prepared query.
     *
     * After this, you can use resultset as you would have called it via DB::execute().
     *
     *
     * @uses   DB
     */
    public function execute()
    {
        if ($this->stmt) {
            try {
                $this->stmt->execute();
                unset($this->fields);
                if ($this->stmt->columnCount()) {
                    $this->rows = $this->stmt->fetchAll(PDO::FETCH_OBJ);
                } else {
                    $this->rows = array();
                }
                $this->index = 0;
                $this->recordCount = count($this->rows);
                $this->column = 1;
            } catch (Exception $e) {
                Debug::__print($this->stmt);
                throw new DB_Exception('Executing a prepared query failed.', 0, $e);
            }
            DB::$querycount++;
        } else {
            throw new DB_Exception('Program attempted to execute query twice.');
        }

        return $this;
    }

    /**
     * Returns current row as an stdClass object and moves pointer to next row.
     *
     *
     * @return mixed stdClass or false if there are no rows
     */
    public function fetch()
    {
        $retval = $this->current();
        $this->next();

        return $retval;
    }

    /**
     * @return array
     */
    public function fetchAll()
    {
        return $this->rows;
    }

    /**
     * @param int $idx
     * @return bool|null
     */
    public function fetchColumn($idx = 0)
    {
        $vals = $this->fetch();

        if ($vals === false) {
            return false;
        }

        $this->next();
        $vals = array_values((array) $vals);

        return isset($vals[$idx]) ? $vals[$idx] : null;
    }

    /**
     * @return bool|mixed
     */
    public function current()
    {
        if (isset($this->rows[$this->index])) {
            return clone $this->rows[$this->index];
        } else {
            return false;
        }
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @return bool|void
     */
    public function next()
    {
        if ($this->index < $this->recordCount) {
            $this->index++;
            unset($this->fields);

            return true;
        }

        return false;
    }

    /**
     *
     */
    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * @param $position
     */
    public function seek($position)
    {
        $this->index = $position;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->rows[$this->index]);
    }

    /**
     * @return int
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }
}
