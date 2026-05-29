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
     * 创建类的实例
     *
     * @param string $url 需要解析的URL
     * @param string $numericPrefix http_build_query 的可选参数，用于构建查询字符串
     * @param string $argSeparator  http_build_query 的可选参数，用于构建查询字符串
     * @param int    $encodingType  http_build_query 的可选参数，用于构建查询字符串
     * @return URI
     */
    public static function make($url, $numericPrefix = '', $argSeparator = '&', $encodingType = \PHP_QUERY_RFC1738)
    {
        return new static($url, $numericPrefix, $argSeparator, $encodingType);
    }

    /**
     * 创建类的实例
     *
     * @param string $url 需要解析的URL
     * @param string $numericPrefix http_build_query 的可选参数，用于构建查询字符串
     * @param string $argSeparator  http_build_query 的可选参数，用于构建查询字符串
     * @param int    $encodingType  http_build_query 的可选参数，用于构建查询字符串
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
     * 解析一个新的URL
     *
     * @param string $url 需要解析的URL
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
        // 处理协议相对URL (//example.com) 以及不规范的 (://example.com)
        if (strncmp($url, '//', 2) === 0) {
            $scheme = isset($this->scheme) ? $this->scheme : self::DEFAULT_SCHEME;
            $url = $scheme . ':' . $url;
        }

        return $url;
    }

    protected function protocolAndPathRelative($url)
    {
        // 处理以 :/// 开头的URL
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
        // PHP 8.1 修复: parse_str() 不再接受 null 参数
        parse_str((string) $this->query, $this->params);
    }

    /**
     * 获取原始URL
     *
     * @return string
     */
    public function original()
    {
        return $this->original;
    }

    /**
     * 使用相对URL更新URI实例。
     * 该URL可以是完整的URL、绝对路径或相对路径。
     * 也可以包含查询字符串和片段。
     *
     * @param string $url 相对URL
     * @return URI
     */
    public function relative($url)
    {
        // 这是一个无方案的相对URL
        if (strncmp($url, ':///', 4) === 0) {
            $url = $this->protocolAndPathRelative($url);
            return self::make($url);
        }

        // 这是一个缺少方案的完整URL
        if (strpos($url, '://') !== false) {
            $url = $this->protocolRelative($url);
            return self::make($url);
        }

        // 带查询参数的路径
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
     * 获取/设置路径扩展名
     *
     * @param string $extension 扩展名
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
     * 获取/设置查询字符串参数
     *
     * @param array $value 参数数组
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
     * 向查询字符串参数中添加新参数
     *
     * @param string $key   点分键名
     * @param mixed  $value 值
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
     * 从查询字符串参数中删除一个参数
     *
     * @param string|callable $keyOrCallback 点分键名或一个回调函数，若回调返回true则删除该元素
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
     * 设置查询字符串参数
     *
     * @param string $key   点分键名
     * @param mixed  $value 值
     * @return URI
     */
    public function set($key, $value)
    {
        return $this->dot($key, $this->params, function ($key, &$array) use ($value) {
            $array[$key] = $value;
        });
    }

    /**
     * 获取查询字符串参数
     *
     * @param string $key 点分键名
     * @return mixed
     */
    public function get($key)
    {
        return $this->dot($key, $this->params, function ($key, &$array) {
            return $array[$key];
        });
    }

    /**
     * 检查参数是否存在
     *
     * @param string $key 点分键名
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
     * 获取/设置查询字符串为URL编码的字符串
     *
     * @param string $query 查询字符串
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
     * 动态设置/获取 URI::PROPERTIES 属性。
     *
     * @param string $method    方法名
     * @param array  $arguments 参数数组
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
     * 渲染新的URL
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
