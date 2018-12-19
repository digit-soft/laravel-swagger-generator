<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\DumperYaml;
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
}
