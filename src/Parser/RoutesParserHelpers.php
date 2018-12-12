<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Support\Str;

trait RoutesParserHelpers
{
    /**
     * Normalize route URI
     * @param string $uri
     * @param bool $forYml
     * @return bool|mixed|string
     */
    protected function normalizeUri($uri, $forYml = false)
    {
        $uri = '/' . ltrim($uri, '/');
        $uri = str_replace('?}', '}', $uri);
        if ($forYml) {
            if (($stripBaseUrl = config('swagger-generator.stripBaseUrl', null)) !== null && strpos($uri, $stripBaseUrl) === 0) {
                $uri = substr($uri, strlen($stripBaseUrl));
            }
        }

        return $uri;
    }

    /**
     * Get array element by string key (camel|snake)
     * @param array  $array
     * @param string $key
     * @return mixed|null
     */
    protected static function getArrayElemByStrKey(array $array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        $keyCamel = Str::camel($key);
        return $array[$keyCamel] ?? null;
    }

    /**
     * Merge arrays
     * @param array $a
     * @param array $b
     * @return array
     */
    protected static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }
}
