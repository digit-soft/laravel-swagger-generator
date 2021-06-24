<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Support\Str;

/**
 * Trait RoutesParserHelpers
 * @property array $components
 */
trait RoutesParserHelpers
{
    /**
     * Normalize route URI.
     *
     * @param  string $uri
     * @param  bool   $forYml
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
     * Get component by key ant type.
     *
     * @param  string $key
     * @param  string $type
     * @return null
     */
    protected function getComponent($key, $type = self::COMPONENT_RESPONSE)
    {
        if (empty($key)) {
            return null;
        }

        return $this->components[$type][$key] ?? null;
    }

    /**
     * Set component.
     *
     * @param  array  $component
     * @param  string $key
     * @param  string $type
     */
    protected function setComponent($component, $key, $type = self::COMPONENT_RESPONSE)
    {
        if (empty($key)) {
            return;
        }
        $this->components[$type][$key] = $component;
    }

    /**
     * Get component reference name.
     *
     * @param  string $key
     * @param  string $type
     * @return string
     */
    protected function getComponentReference($key, $type = self::COMPONENT_RESPONSE)
    {
        $keys = ['components', $type, $key];

        return '#/' . implode('/', $keys);
    }

    /**
     * Get array element by string key (camel|snake).
     *
     * @param  array  $array
     * @param  string $key
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
