<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Support\Str;

/**
 * Trait RoutesParserHelpers
 *
 * @property array $components
 */
trait RoutesParserHelpers
{
    /**
     * Normalize route URI.
     *
     * @param  string $uri
     * @param  bool   $forYml
     * @return string
     */
    protected function normalizeUri(string $uri, bool $forYml = false): string
    {
        $uri = '/' . ltrim($uri, '/');
        $uri = str_replace('?}', '}', $uri);
        if ($forYml) {
            $stripBaseUrl = config('swagger-generator.stripBaseUrl');
            if ($stripBaseUrl !== null && str_starts_with($uri, $stripBaseUrl)) {
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
     * @return array|null
     */
    protected function getComponent(string $key, string $type = self::COMPONENT_RESPONSE): ?array
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
    protected function setComponent(array $component, string $key, string $type = self::COMPONENT_RESPONSE): void
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
    protected function getComponentReference(string $key, string $type = self::COMPONENT_RESPONSE): string
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
    protected static function getArrayElemByStrKey(array $array, string $key): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        $keyCamel = Str::camel($key);

        return $array[$keyCamel] ?? null;
    }
}
