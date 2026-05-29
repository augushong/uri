<?php

namespace Ulthon\URI;

/**
 * @method scheme($scheme = null)
 * @method user($user = null)
 * @method pass($pass = null)
 * @method host($host = null)
 * @method port($port = null)
 * @method path($path = null)
 * @method fragment($fragment = null)
 */
class URI
{
    const PROPERTIES = ['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'];
    const HTTPS_SCHEME = 'https';
    const DEFAULT_SCHEME = self::HTTPS_SCHEME;

    protected $original;

    protected $scheme;
    protected $host;
    protected $port;
    protected $user;
    protected $pass;
    protected $path;
    protected $query;
    protected $fragment;

    protected $params = [];

    protected $numericPrefix = '';
    protected $argSeparator = '&';
    protected $encodingType = \PHP_QUERY_RFC1738;

    /**
     * Create an instance of the class
     *
     * @param string $url
     * @param string $numericPrefix
     * @param string $argSeparator
     * @param int    $encodingType
     * @return URI
     */
    public static function make($url, $numericPrefix = '', $argSeparator = '&', $encodingType = \PHP_QUERY_RFC1738)
    {
        return new static($url, $numericPrefix, $argSeparator, $encodingType);
    }

    /**
     * Create an instance of the class
     *
     * @param string $url
     * @param string $numericPrefix
     * @param string $argSeparator
     * @param int    $encodingType
     * @return URI
     */
    public function __construct($url, $numericPrefix = '', $argSeparator = '&', $encodingType = \PHP_QUERY_RFC1738)
    {
        $this->numericPrefix = $numericPrefix;
        $this->argSeparator = $argSeparator;
        $this->encodingType = $encodingType;
        $this->parse($url);
    }

    /**
     * Parse a new url
     *
     * @param string $url
     * @return URI
     */
    public function parse($url)
    {
        $url = $this->protocolRelative($url);

        $this->original = $url;

        $parsed = parse_url($url);

        foreach (self::PROPERTIES as $property) {
            $this->$property = isset($parsed[$property]) ? $parsed[$property] : null;
        }

        $this->path = isset($this->path) ? $this->path : '/';

        $this->parseQuery();

        return $this;
    }

    protected function protocolRelative($url)
    {
        // Handle protocol-relative URLs (//example.com) and malformed (://example.com)
        if (strncmp($url, '//', 2) === 0) {
            $scheme = isset($this->scheme) ? $this->scheme : self::DEFAULT_SCHEME;
            $url = $scheme . ':' . $url;
        }

        return $url;
    }

    protected function protocolAndPathRelative($url)
    {
        // Handle urls that start with :///
        if (strncmp($url, ':///', 4) === 0) {
            $scheme = isset($this->scheme) ? $this->scheme : self::DEFAULT_SCHEME;

            if (is_null($this->port)) {
                $url = $scheme . '://' . $this->host . substr($url, 3);
            } else {
                $url = $scheme . '://' . $this->host . ':' . $this->port . substr($url, 3);
            }
        }

        return $url;
    }

    protected function parseQuery()
    {
        // PHP 8.1 fix: parse_str() no longer accepts null
        parse_str((string) $this->query, $this->params);
    }

    /**
     * Get the original url
     *
     * @return string
     */
    public function original()
    {
        return $this->original;
    }

    /**
     * Update the URI instance with a relative url.
     *
     * @param string $url
     * @return URI
     */
    public function relative($url)
    {
        // It's a schemeless relative URL
        if (strncmp($url, ':///', 4) === 0) {
            $url = $this->protocolAndPathRelative($url);
            return self::make($url);
        }

        // It's a full URL without scheme
        if (strpos($url, '://') !== false) {
            $url = $this->protocolRelative($url);
            return self::make($url);
        }

        // Path with params
        if (strpos($url, '?') !== false) {
            list($path, $this->query) = explode('?', $url, 2);

            if (strpos($this->query, '#') !== false) {
                list($this->query, $this->fragment) = explode('#', $this->query, 2);
            } else {
                $this->fragment = null;
            }

            $this->parseQuery();
        } else {
            $path = $url;
        }

        $path = trim($path);

        if (strncmp($path, '/', 1) === 0) {
            $this->path = $path;
        } else {
            if (strncmp($path, './', 2) === 0) {
                $path = substr($path, 2);
            }
            $base = substr($this->path, 0, strrpos($this->path, '/') + 1);
            $this->path = $base . $path;
        }

        $tokens = explode('/', $this->path);

        while (in_array('..', $tokens)) {
            for ($i = 0; $i < count($tokens); $i++) {
                if ($tokens[$i] === '..') {
                    unset($tokens[$i]);
                    unset($tokens[$i - 1]);
                    $tokens = array_values($tokens);
                    break;
                }
            }
        }

        $this->path = implode('/', $tokens);

        return $this;
    }

    /**
     * Get / set the path extension
     *
     * @param string $extension
     * @return string|$this|null
     */
    public function extension($extension = null)
    {
        $pos = strrpos($this->path, '.');

        if (func_num_args() === 0) {
            if ($pos === false) {
                return null;
            } else {
                return substr($this->path, $pos + 1);
            }
        } else {
            if ($pos === false) {
                $this->path .= '.' . $extension;
            } else {
                $this->path = substr($this->path, 0, $pos + 1) . $extension;
            }
        }

        return $this;
    }

    /**
     * Get / set the querystring parameters
     *
     * @param array $value
     * @return array|$this
     */
    public function params(array $value = array())
    {
        if (func_num_args() === 0) {
            return $this->params;
        }

        $this->params = $value;

        return $this;
    }

    /**
     * Add a new parameter to the querystring parameters
     *
     * @param string $key
     * @param mixed  $value
     * @return URI
     */
    public function add($key, $value)
    {
        return $this->dot($key, $this->params, function ($key, &$array) use ($value) {
            if (isset($array[$key])) {
                if (is_array($array[$key])) {
                    $array[$key][] = $value;
                } else {
                    $array[$key] = array($array[$key], $value);
                }
            } else {
                $array[$key] = $value;
            }
        });
    }

    /**
     * Remove a parameter from the querystring parameters
     *
     * @param string|callable $keyOrCallback
     * @return URI
     */
    public function remove($keyOrCallback)
    {
        if (is_callable($keyOrCallback)) {
            $dotted = $this->dotted($this->params);

            foreach ($dotted as $key => $value) {
                if ($keyOrCallback($key, $value)) {
                    $this->dot($key, $this->params, function ($key, &$array) {
                        unset($array[$key]);
                    });
                }
            }

            return $this;
        }

        return $this->dot($keyOrCallback, $this->params, function ($key, &$array) {
            unset($array[$key]);
        });
    }

    /**
     * Set a parameter of the querystring parameters
     *
     * @param string $key
     * @param mixed  $value
     * @return URI
     */
    public function set($key, $value)
    {
        return $this->dot($key, $this->params, function ($key, &$array) use ($value) {
            $array[$key] = $value;
        });
    }

    /**
     * Get a parameter of the querystring parameters
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->dot($key, $this->params, function ($key, &$array) {
            return $array[$key];
        });
    }

    /**
     * Check if a parameter exists
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->dot($key, $this->params, function ($key, &$array) {
            return isset($array[$key]);
        });
    }

    // @see https://github.com/laravel/framework/blob/.../Arr.php#L286
    protected function dot($key, &$array, $callback)
    {
        if (!is_array($array) && !($array instanceof \ArrayAccess)) {
            return $this;
        }

        if (strpos($key, '.') === false) {
            $result = $callback($key, $array);
            return isset($result) ? $result : $this;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = array();
            }

            $array = &$array[$key];
        }

        $key = array_shift($keys);

        $result = $callback($key, $array);
        return isset($result) ? $result : $this;
    }

    // @see https://github.com/laravel/framework/blob/.../Arr.php#L109
    protected function dotted($array, $previous = '')
    {
        $results = array();

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, $this->dotted($value, $previous . $key . '.'));
            } else {
                $results[$previous . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get / set the querystring as an encoded string
     *
     * @param string $query
     * @return string|$this
     */
    public function query($query = null)
    {
        if (func_num_args() === 0) {
            return http_build_query($this->params, $this->numericPrefix, $this->argSeparator, $this->encodingType);
        }

        $this->query = $query;
        $this->parseQuery();

        return $this;
    }

    /**
     * Dynamically set / get URI::PROPERTIES properties.
     *
     * @param string $method
     * @param array  $arguments
     * @return mixed|$this
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, self::PROPERTIES)) {
            if (count($arguments) === 0) {
                return $this->$name;
            }
            $this->$name = $arguments[0];

            return $this;
        }
    }

    public function __toString()
    {
        return $this->url();
    }

    /**
     * Render the new url
     *
     * @return string
     */
    public function url()
    {
        $result = !is_null($this->scheme) ? $this->scheme . '://' : '';

        if (!is_null($this->user) && !is_null($this->pass)) {
            $result .= urlencode($this->user) . ':' . urlencode($this->pass) . '@';
        } elseif (!is_null($this->user)) {
            $result .= urlencode($this->user) . '@';
        }

        $result .= $this->host;

        if (!is_null($this->port)) {
            $result .= ':' . $this->port;
        }

        if (!is_null($this->path)) {
            $result .= $this->path;
        }

        if (count($this->params) > 0) {
            $result .= '?' . $this->query();
        }

        if (!is_null($this->fragment)) {
            $result .= '#' . $this->fragment;
        }

        return $result;
    }
}
