<?php
/**
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@amigaone.cc>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2006 Joni Halme <jontsa@amigaone.cc>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2008,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
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
use RuntimeException;
use DomainException;
use OutOfBoundsException;
use LogicException;
use InvalidArgumentException;

use Libvaloa\Debug;

class Db
{

    /**
     * Instance of PDO.
     *
     * @access private
     * @var    PDO
     */
    private $conn;

    /**
     * Amount of not commited/rollbacked transactions started with beginTrans().
     *
     * @access private
     * @var    int
     */
    private $transcnt = 0;

    /**
     * Number of SQL queries executed.
     *
     * @static
     * @access public
     * @var    int
     */
    public static $querycount = 0;

    public $properties = array(
        'db_server' => '',
        'db_host' => '',
        'db_user' => '',
        'db_pass' => '',
        'db_db' => ''
    );

    /**
     * Constructor opens connection to database using PDO.
     *
     * @access public
     * @param string $server   SQL server. defaults to localhost
     * @param string $user     Username at SQL server
     * @param string $pass     Password at SQL server or false if none
     * @param string $database Database to select
     * @param string $dbconn   Database type (mysql,sqlite etc). Defaults to mysql
     * @param mixed  $sqlitedb Optional path to SQLite database
     * @param bool   $pconn    Use persistent connection? Defaults to false
     * @uses   PDO
     */
    public function __construct(
        $server = 'localhost',
        $user,
        $pass = false,
        $database = false,
        $dbconn = 'mysql',
        $pconn = false,
        $initquery = false)
    {
        if ($dbconn === 'postgres') {
            $dbconn = 'pgsql';
        }

        // Assign connection settings to properties for public access
        $this->properties['db_server'] = $dbconn;
        $this->properties['db_host'] = $server;
        $this->properties['db_user'] = $user;
        $this->properties['db_db'] = $database;

        $drivers = PDO::getAvailableDrivers();

        if (!in_array($dbconn, $drivers, true)) {
            throw new RuntimeException("Selected database type is not supported by PDO or PHP is not compiled with the appropriate driver (see www.php.net/pdo).");
        }

        switch ($dbconn) {
            case 'mysql':
                $dsn = "mysql:host={$server};dbname={$database}";
                break;
            case 'sqlite':
                if (file_exists($database) && !is_readable($database)) {
                    throw new RuntimeException("Selected SQLite database is not readable. Please check your database settings.");
                }
                $dsn = "sqlite:{$database}";
                break;
            case 'pgsql':
                $dsn = "pgsql:host={$server} port=5432 dbname={$database} user={$user} password={$pass}";
                break;
            default:
                throw new DomainException("Unsupported database type. Can't create database connection.");
        }

        $attr = array();
        $attr[PDO::ATTR_PERSISTENT] = (bool) $pconn;

        if ($dbconn === 'mysql' && !empty($initquery)) {
            $attr[PDO::MYSQL_ATTR_INIT_COMMAND] = $initquery;
        }

        $this->conn = new PDO($dsn, $user, $pass, $attr);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($dbconn === 'sqlite') {
            $this->conn->setAttribute(PDO::ATTR_TIMEOUT, 60);
        }

        if ($dbconn != 'mysql' && !empty($initquery)) {
           $this->exec($initquery);
        }
    }

    /**
     * Class member get overload method.
     *
     * Currently supported is transCnt.
     *
     * @access public
     * @param  string $k
     * @return mixed
     */
    public function __get($k)
    {
        switch ($k) {
            case 'transCnt':
                return $this->transcnt;

        }

        throw new OutOfBoundsException("Program tried to access a non-existant member ".__CLASS__."::{$k}.");
    }

    /**
     * Executes SQL query and returns results in DB_ResultSet object.
     *
     * @access public
     * @param  string       $query SQL query
     * @return DB_ResultSet
     * @uses   Common_Exception
     * @uses   DB_ResultSet
     */
    public function execute($query)
    {
        try {
            Debug::__print($query);

            $stmt = $this->conn->query($query);
            self::$querycount++;

            return new ResultSet($stmt, true);
        } catch (Exception $e) {
            throw new DBException("SQL query failed.", 0, $e);
        }
    }

    /**
     * Prepares an SQL query without executing it.
     *
     * Use this method when you want to insert variables other than
     * strings to database. It is also usefull when you need to make same
     * query multiple times with different values.
     *
     * @access public
     * @param  string       $query SQL query
     * @return DB_ResultSet
     * @uses   DB_ResultSet
     * @uses   Common_Exception
     */
    public function prepare($query)
    {
        if (empty($query)) {
            throw new DBException("Empty SQL query can't be executed.");
        }

        try {
            if (isset($_GET['debug'])) {
                Debug::__print($query);
            }

            return new ResultSet($this->conn->prepare($query));
        } catch (Exception $e) {
            throw new DBException("Preparing SQL query failed.", 0, $e);
        }
    }

    /**
     * Executes SQL query without returning resultset.
     *
     * This method is similar to execute() but instead of returning ResultSet,
     * it just returns the amount of affected rows and thus is slightly
     * faster when doing INSERT, UPDATE etc queries.
     *
     * @access public
     * @param  string $query SQL query
     * @return int    Number of affected rows
     */
    public function exec($query)
    {
        if (empty($query)) {
            throw new DBException("Empty SQL query can't be executed.");
        }

        try {
            $affected = $this->conn->exec($query);
            self::$querycount++;
        } catch (Exception $e) {
            throw new DBException("SQL query failed.", 0, $e);
        }

        return $affected;
    }

    public function lastInsertID()
    {
        if ($this->conn == 'postgres') {
            throw new DBException("lastInsertID not supported with PostgreSQL, please use RETURNING id");
        }

        try {
            return $this->conn->lastInsertID();
        } catch (Exception $e) {
            throw new DBException("Unable to retrieve identifier for last insert query.");
        }
    }

    public function beginTrans()
    {
        $this->beginTransaction();
    }

    public function commitTrans($ok = true)
    {
        $this->commit($ok);
    }

    public function rollBackTrans()
    {
        $this->rollBack();
    }

    /**
     * Begins database transaction if database supports it.
     *
     * @access public
     * @uses   Common_Exception
     */
    public function beginTransaction()
    {
        try {
            $this->conn->beginTransaction();
            $this->transcnt++;
        } catch (Exception $e) {
            throw new RuntimeException("Could not start database transaction.");
        }
    }

    /**
     * Commits transaction started with beginTrans().
     *
     * @access public
     * @param bool $ok If false, method automatically calls rollBack() and transaction is not committed
     */
    public function commit($ok = true)
    {
        if ($this->transcnt < 1) {
            return;
        }

        try {
            if (!$ok) {
                $this->conn->rollBack();
            } else {
                $this->conn->commit();
            }
            $this->transcnt--;
        } catch (Exception $e) {
            throw new RuntimeException("Could not commit database transaction.");
        }
    }

    /**
     * Cancels transaction started with beginTrans().
     *
     * @access public
     */
    public function rollBack()
    {
        if ($this->transcnt < 1) {
            throw new LogicException("Program attempted to cancel transaction without starting one.");
        }

        try {
            $this->conn->rollBack();
            $this->transcnt--;
        } catch (Exception $e) {
            throw new RuntimeException("Could not roll back database transaction.");
        }
    }

}

class ResultSet implements Iterator
{

    /**
     * Array of rows from executed SQL query.
     *
     * @var    array
     */
    private $rows = array();

    private $recordCount = 0;

    /**
     * Current row in resultset.
     * @var    int
     */
    private $index = 0;

    /**
     * PDOStatement if resultset was created with DB::prepare().
     *
     * @param  mixed
     */
    private $stmt = false;

    /**
    * Binding column count
    * @param  int
    */
    private $column = 1;

    /**
     * Constructor.
     *
     * @access public
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
     * @access public
     * @return mixed
     */
    public function __get($k)
    {
        switch ($k) {
            case "fields":
                if (isset($this->fields)) {
                    return $this->fields;
                } elseif (isset($this->rows[$this->index])) {
                    $this->fields = array();
                    foreach ($this->rows[$this->index] as $k=>$v) {
                        $this->fields[$k] = $v;
                    }

                    return $this->fields;
                }

                return array();
            case "EOF":
                return ($this->index>=$this->recordCount);
        }
    }

    public function set($value, $key = false)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindValue($key, $value);

            return $this;
        }

        throw new LogicException("Program attempted to set parameter to an executed SQL query.");
    }

    public function setLob($value, $key = false)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindValue($key, $value, PDO::PARAM_LOB);

            return $this;
        }

        throw new LogicException("Program attempted to set parameter to an executed SQL query.");
    }

    public function bind(&$value, $key = false)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindParam($key, $value);

            return $this;
        }

        throw new LogicException("Program attempted to bind parameter to an executed SQL query.");
    }

    public function bindLob($value, $key = false)
    {
        if ($this->stmt) {
            if (!$key) {
                $key = $this->column++;
            }
            $this->stmt->bindParam($key, $value, PDO::PARAM_LOB);

            return $this;
        }

        throw new LogicException("Program attempted to bind parameter to an executed SQL query.");
    }

    /**
     * Executes a prepared query.
     *
     * After this, you can use resultset as you would have called it via DB::execute().
     *
     * @access public
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
                throw new DB_Exception("Executing a prepared query failed.", 0, $e);
            }
            DB::$querycount++;
        } else {
            throw new DB_Exception("Program attempted to execute query twice.");
        }

        return $this;
    }

    /**
     * Returns current row as an stdClass object and moves pointer to next row.
     *
     * @access public
     * @return mixed stdClass or false if there are no rows
     */
    public function fetch()
    {
        $retval = $this->current();
        $this->next();

        return $retval;
    }

    public function fetchAll()
    {
        return $this->rows;
    }

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

    public function current()
    {
        if (isset($this->rows[$this->index])) {
            return clone $this->rows[$this->index];
        } else {
            return false;
        }
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        if ($this->index < $this->recordCount) {
            $this->index++;
            unset($this->fields);

            return true;
        }

        return false;
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function seek($position)
    {
        $this->index = $position;
    }

    public function valid()
    {
        return isset($this->rows[$this->index]);
    }

}
