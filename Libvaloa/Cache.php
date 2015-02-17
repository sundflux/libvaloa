<?php
/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
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

namespace Libvaloa;

use Memcached;

/**
 * Memcached caching class
 */
class Cache
{

    /**
     * Memcached server settings
     *
     * @var type
     */
    public $properties = array(
        'host'         => 'localhost',
        'port'         => 11211,
        'expires'      => 300, // 5 minute cache by default
        'defaultPrefix'=> 'Libvaloa_Cache',
        'empty'        => '__CACHE_NULL__'
    );

    private $connection;
    private $connectionName;

    public function __construct($connectionName = false)
    {
        $this->connectionName = $connectionName;
        if (!$this->connectionName) {
            $this->connectionName = $this->properties['defaultPrefix'];
        }
    }

    /**
     * Open connection to memcached server
     */
    public function openConnection()
    {
        $this->connection = new Memcached($this->connectionName);
        $this->connection->setOption(Memcached::OPT_COMPRESSION, false);
        $servers = $this->connection->getServerList();

        // Check if the server is already in the memcached pool
        foreach ($servers as $server) {
            if ($server['host'] == $this->properties['host'] && $server['port'] == $this->properties['port']) {
                return;
            }
        }

        $this->connection->addServer($this->properties['host'], $this->properties['port']);
    }

    /**
     * Set key/value pair to global memcached caching scope.
     * Alias for set()
     *
     * @param  type $key
     * @param  type $value
     * @return bool
     */
    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }

    /**
     * Get global cache value by key.
     * Alias for get()
     *
     * @param  type  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Set key/value pair to global memcached caching scope.
     *
     * @param  type $key
     * @param  type $value
     * @return bool
     */
    public function set($key, $value)
    {
        if (!$this->connection) {
            $this->openConnection();
        }

        if (empty($value) || $value == null) {
            $value = $this->properties['empty'];
        }

        $v = $this->connection->add($key, $value, $this->properties['expires']);

        if (!$v || $v === null) {
            return false;
        }

        return $v;
    }

    /**
     * Get global cache value by key.
     *
     * @param  type  $key
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->connection) {
            $this->openConnection();
        }

        $v = $this->connection->get($key);

        if (!$v || $v === null || $v == '') {
            return false;
        }

        return $v;
    }

    /**
     * Set session-specific key/value pair to memcached.
     *
     * @param  type    $key
     * @param  type    $value
     * @return boolean
     */
    public function _set($key, $value)
    {
        if (!$this->connection) {
            $this->openConnection();
        }

        $v = $this->connection->add(session_id() . $key, $value, $this->properties['expires']);

        if (!$v || $v === null) {
            return false;
        }

        return $v;
    }

    /**
     * Get session-specific cache value by key.
     *
     * @param  type  $key
     * @return mixed
     */
    public function _get($key)
    {
        if (!$this->connection) {
            $this->openConnection();
        }

        $v = $this->connection->get(session_id() . $key);

        if (!$v || $v === null) {
            return false;
        }

        return $v;
    }

    public function delete($key)
    {
        $search = $key;
        $keys = $this->connection->getAllKeys();

        if ($search !== false) {
            foreach ($keys as $index => $key) {
                if (strpos($key, $search) !== false) {
                    unset($keys[$index]);
                } else {
                    $this->connection->delete($key);
                }
            }
        }

        return $keys;
    }

    public function _delete($key)
    {
        return $this->delete(session_id() . $search);
    }

}
