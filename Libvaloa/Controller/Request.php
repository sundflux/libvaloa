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
 * 2008,2009,2013,2014 Tarmo Alexander Sundström <ta@sundstrom.im>
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
 * Controller Request object.
 *
 * $uri must always contain:
 * host[/path][/index.php]/controller[/method][/params][?getparams]
 * without http[s]:// prefix.
 *
 * If method is not found, it is appended to parameters and no method is called automatically.
 * If controller is not found, it is appended to parameters and default controller is opened
 * Parameters can be used as variable1/value1/variable2/value2 or value1/value2/value3 etc
 */

namespace Libvaloa\Controller;

class Request
{
    private static $instance = false;

    private $baseuri = array();       // host (with http[s]:// prefix) and path
    private $controller = false;      // requested controller to load
    private $method = 'index';        // requested method to call from controller
    private $parameters = array();    // parameters for controller
    private $protocol = 'http';

    private $ajax = false;
    private $json = false;

    public function __construct()
    {
        $tmp = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
        $uri = $_SERVER['HTTP_HOST'].$tmp.str_replace(str_replace('index.php', '', $tmp), '/', $_SERVER['REQUEST_URI']);

        // http/https autodetect
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $prefix = 'https://';
        } else {
            $prefix = 'http://';
        }

        // url should be without http[s]:// prefix and contain
        // host[/path][/index.php]/controller[/method][/params][?getparams]
        $this->baseuri['host'] = $prefix.$_SERVER['HTTP_HOST'];

        // Route when rewrite..
        if (strpos($uri, 'index.php') === false) {
            $uri = str_replace($_SERVER['HTTP_HOST'],
                $_SERVER['HTTP_HOST'].'index.php', $uri);
        }

        list($host, $route) = explode('index.php', $uri, 2);
        $route = str_replace('index.php', '', $route);
        $this->baseuri['path'] = str_ireplace($_SERVER['HTTP_HOST'], '', $host);

        // strip GET parameters, we will add them later
        list($route) = explode('?', $route, 2);

        if (substr($route, 0, 1) === '/') {
            $route = substr($route, 1);
        }

        $route = explode('/', $route);

        // get controller from route
        if (isset($route[0])) {
            $this->controller = ucfirst(array_shift($route));
        }

        // get method from route
        if (isset($route[0])) {
            $this->method = array_shift($route);
        }

        // rest are parameters
        $this->parameters = array_map(array($this, 'decodeRouteParam'), $route);

        // Protocol
        $this->protocol = 'http';

        if (isset($_SERVER['HTTPS'])) {
            $this->protocol = ($_SERVER['HTTPS']
                && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
        }

        if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $this->protocol = 'https';
        }

        self::$instance = $this;

        // ajax autodetect
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->ajax = true;

            if (isset($_SERVER['HTTP_ACCEPT']) && in_array('application/json',
                explode(',', $_SERVER['HTTP_ACCEPT']), true)) {
                $this->json = true;
            }
        }
    }

    /**
     * Returns Request instance.
     *
     * @return Request
     */
    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }

        return new Request();
    }

    /**
     * This method is called from controller if selected method does not exist.
     * We assume that second parameter is not meant as method but as a parameter.
     *
     * @note This method should never ever be called after shiftController().
     */
    public function shiftMethod()
    {
        if ($this->method && $this->method != 'index') {
            array_unshift($this->parameters, $this->method);
        }

        $this->method = false;
    }

    /**
     * This method is called from controller if selected controller does not exist.
     * We assume that first parameter is not meant as controller name but as
     * a parameter.
     */
    public function shiftController()
    {
        if ($this->controller) {
            array_unshift($this->parameters, $this->controller);
        }

        $this->controller = false;
    }

    public function shiftParam()
    {
        array_shift($this->parameters);
    }

    /**
     * Sets controller to load.
     */
    public function setController($controller)
    {
        $this->controller = ucfirst($controller);
    }

    /**
     * Sets method to call from controller.
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Sets parameters for controller.
     */
    public function setParams($params)
    {
        if (is_array($params)) {
            $this->parameters = $params;
        } else {
            $this->parameters = explode('/', $params);
        }
    }

    /*
     * Set protocol
     */
    public function setProtocol($protocol)
    {
        $protocols = array(
            'http',
            'https',
            'h2-17', // http/2 secure, draft 17
            'h2-14', // http/2 secure, draft 14
            'h2c-17', // http/2 non-secure, draft 17
            'h2c-14', // http/2 non-secure, draft 17
        );

        if (!in_array($protocol, $protocols)) {
            $protocol = 'http';
        }

        $this->protocol = $protocol;
    }

    /**
     * Returns the parameters and their values from current request.
     *
     * @param bool $string If true, return value is request string,
     *                     otherwise its an array
     *
     * @return mixed
     */
    public function getParams($string = false)
    {
        if (!$string) {
            return $this->parameters;
        }

        return '/'.implode('/', $this->parameters);
    }

    /**
     * Returns name of requested controller.
     */
    public function getController($full = true)
    {
        if (!$full) {
            $tmp = explode('_', $this->controller);

            return ucfirst($tmp[0]);
        }

        return ucfirst($this->controller);
    }

    public function getMainController()
    {
        return $this->getController(false);
    }

    public function getChildController()
    {
        $tmp = explode('_', $this->controller);

        if (isset($tmp[1])) {
            return ucfirst($tmp[1]);
        }

        return ucfirst($this->getMainController());
    }

    /**
     * Returns name of requested method.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Returns a single parameter by its position in parameters or by its key.
     */
    public function getParam($k)
    {
        if (is_int($k)) {
            return isset($this->parameters[$k]) ? $this->parameters[$k] : false;
        } else {
            $k = array_search($k, $this->parameters);
            if ($k !== false && isset($this->parameters[$k + 1])) {
                return $this->parameters[$k + 1];
            }

            return false;
        }
    }

    /**
     * Returns the host-part of the current request IF available.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->baseuri['host'];
    }

    /**
     * Returns the base path.
     * The path does not contain index.php.
     *
     * @return string
     */
    public function getPath()
    {
        return rtrim(dirname(substr($_SERVER['SCRIPT_FILENAME'],
            strlen($_SERVER['DOCUMENT_ROOT']))), '/');
    }

    /**
     * Returns the full route to the current request without the leading /.
     * For example "my_controller/method/param1/value1".
     * Parameters in route are encoded.
     *
     * @return string
     */
    public function getCurrentRoute()
    {
        $params = array_map('self::encodeRouteParam', $this->parameters);

        return $this->controller.'/'.($this->method !== false
            && $this->method != 'index' ? $this->method.'/' : '').
            implode('/', $params);
    }

    /**
     * Returns host and path to the website with http[s]:// prefix.
     *
     * @param bool $noautoindex If true, index.php will not be
     *                          automatically appended to url.
     *
     * @return string
     */
    public function getBaseUri($noautoindex = false)
    {
        // Basehref
        return $this->protocol.'://'.$_SERVER['HTTP_HOST'].$this->getPath();
    }

    /**
     * Returns full URI of the current website with controller,
     * method and controller parameters.
     */
    public function getUri()
    {
        return $this->getBaseUri().'/'.$this->getCurrentRoute();
    }

    public function isAjax($val = null)
    {
        if ($val !== null) {
            $this->ajax = (bool) $val;
        }

        return $this->ajax;
    }

    public function isJson($val = null)
    {
        if ($val !== null) {
            $this->json = (bool) $val;
        }

        return $this->json;
    }

    private function decodeRouteParam($val)
    {
        if (substr($val, 0, 5) === '$enc$') {
            return base64_decode(str_replace('.', '/',
                urldecode(substr($val, 5))));
        } else {
            return urldecode($val);
        }
    }

    public static function encodeRouteParam($val)
    {
        if (strpos($val, '/') !== false) {
            return "\$enc\$".urlencode(str_replace('/', '.', base64_encode($val)));
        } else {
            return urlencode($val);
        }
    }
}
