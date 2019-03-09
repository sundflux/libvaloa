<?php

/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.io>.
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2010 Tarmo Alexander Sundström <ta@sundstrom.io>
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
namespace Libvaloa\Db;

use Libvaloa\Debug;
use stdClass;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Class Object
 * @package Libvaloa\Db
 */
class Item
{
    /**
     * @var bool
     */
    private $db;

    /**
     * @var bool
     */
    private $struct;

    /**
     * @var string
     */
    private $primaryKey = 'id'; // name of the primary key field in table

    /**
     * @var bool
     */
    private $lateload = false;

    /**
     * @var stdClass
     */
    private $data;

    /**
     * @var bool
     */
    private $modified = false;

    /**
     * @var bool
     */
    private $columns = false;

    /**
     * Constructor - give the name of the target table.
     *
     * @param string $structName Target table
     */
    public function __construct($table = false, $dbconn = false)
    {
        if (!$table) {
            throw new InvalidArgumentException('Data structure name needed.');
        }

        if (!$dbconn) {
            throw new InvalidArgumentException('DB connection needed.');
        }

        $this->struct = $table;
        $this->data = new stdClass();
        $this->db = $dbconn;
    }

    /**
     * Set primary key field, defaults to id.
     *
     * @param string $key Primary key field
     */
    public function primarykey($key)
    {
        $this->primaryKey = $key;
    }

    /**
     * Force validating of column names. Only names set in $columns array will
     * be included in the query.
     *
     * @param array $columns Allowed table columns as array
     */
    public function columns($columns)
    {
        $this->data = (object) $columns;
    }

    /**
     *
     */
    private function detectColumns()
    {
        // Columns already set
        if ($this->columns) {
            return;
        }

        // Detect columns
        switch ($this->db->properties['db_server']) {
            default:

                // Detect columns
                $query = '
                    SELECT column_name, data_type, column_key
                    FROM information_schema.columns
                    WHERE table_name = ?
                    AND table_schema = ?';

                $stmt = $this->db->prepare($query);
                $stmt->set($this->struct);
                $stmt->set($this->db->properties['db_db']);

                try {
                    $stmt->execute();
                    foreach ($stmt as $row) {
                        $columns[$row->column_name] = null;
                        if ($row->column_key == 'PRI') {
                            $this->primaryKey($row->column_name);
                        }
                    }

                    if (isset($columns)) {
                        $this->columns($columns);
                    }

                    $this->columns = true;
                } catch (Exception $e) {
                    Debug::__print('Could not query table structure');
                    Debug::__print($e->getMessage());
                }
            break;
        }
    }

    /**
     * @param $field
     * @return null|string
     */
    public function __get($field)
    {
        $this->detectColumns();

        if (is_numeric($this->lateload)) {
            $this->_byID();
        }

        if ($field == 'primaryKey') {
            return $this->primaryKey;
        }

        return isset($this->data->$field) ? $this->data->$field : null;
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->detectColumns();

        if (is_numeric($this->lateload)) {
            $this->_byID();
        }

        if ($key == $this->primaryKey) {
            return;
        }

        foreach ($this->data as $_tmpk => $_tmpv) {
            if ($_tmpk == $key) {
                if ($value !== $_tmpv) {
                    $this->data->$key = $value;
                    $this->modified = true;
                }
            }
        }
    }

    /**
     * @param $id
     */
    public function byID($id)
    {
        $this->lateload = $id;
    }

    private function _byID()
    {
        $this->detectColumns();

        $stmt = $this->db->prepare("
            SELECT * FROM {$this->struct}
            WHERE {$this->primaryKey} = ?");

        $stmt->set((int) $this->lateload);
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row === false) {
            throw new OutOfBoundsException('Selected row does not exist.');
        }

        $this->data = $row;
        $this->lateload = false;
    }

    /**
     *
     */
    public function save()
    {
        if (is_numeric($this->lateload)) {
            $this->_byID();
        }

        if ($this->modified === false) {
            return;
        }

        if (!isset($this->data->{$this->primaryKey})) {
            $this->data->{$this->primaryKey} = null;
        }

        $fields = array();
        foreach ($this->data as $key => $val) {
            $fields[$key] = '?';
        }

        if (!is_numeric($this->data->{$this->primaryKey})) {
            $query = "
                INSERT INTO {$this->struct} (`".implode('`,`', array_keys($fields)).'`)
                VALUES ('.implode(',', $fields).')';

            if ($this->db->properties['db_server'] === 'postgres') {
                $query .= " RETURNING {$this->primaryKey}";
            }
        } else {
            $query = "
                UPDATE {$this->struct}
                SET `".implode('` = ?,`', array_keys($fields))."` = ?
                WHERE {$this->primaryKey} = ?";
        }

        unset($fields);
        $stmt = $this->db->prepare($query);

        foreach ($this->data as $val) {
            $stmt->set($val);
        }

        if (is_numeric($this->data->{$this->primaryKey})) {
            $stmt->set((int) $this->data->{$this->primaryKey});
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            Debug::__print($e->getMessage());
        }

        if (!is_numeric($this->data->{$this->primaryKey})) {
            $this->data->{$this->primaryKey} = (int) $this->db->properties['db_server'] === 'postgres' ? $stmt->fetchColumn() : $this->db->lastInsertID();
        }

        return $this->data->{$this->primaryKey};
    }

    /**
     *
     */
    public function delete()
    {
        if (is_numeric($this->lateload)) {
            $this->_byID();
        }

        if (!isset($this->data->{$this->primaryKey})
            || !is_numeric($this->data->{$this->primaryKey})) {
            return;
        }

        $query = "
            DELETE FROM {$this->struct}
            WHERE {$this->primaryKey} = ?";

        $stmt = $this->db->prepare($query);
        $stmt->set((int) $this->data->{$this->primaryKey});

        $stmt->execute();
    }
}
