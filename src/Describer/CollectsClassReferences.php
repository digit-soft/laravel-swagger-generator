<?php

namespace DigitSoft\Swagger\Describer;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\RoutesParser;

/**
 * For `Variable` class. Adds a possibility to collect class data references.
 */
trait CollectsClassReferences
{
    private static bool $collectClassRefs = true;
    private static array $collectedClassReferences = [];
    private static array $collectedClassReferencesKeys = [];

    /**
     * Enable/disable collection of the class references.
     *
     * @param  bool $value
     * @param  bool $clear
     * @return void
     */
    public static function collectClassReferences(bool $value = true, bool $clear = false): void
    {
        static::$collectClassRefs = $value;
        if ($clear) {
            static::$collectedClassReferences = [];
        }
    }

    /**
     * Get collected class definitions for references.
     *
     * @return array
     */
    public static function getCollectedClassReferences(): array
    {
        return static::$collectedClassReferences;
    }

    /**
     * Populate target array with collected class references + returns all the references.
     *
     * @param  array $target
     * @return array
     */
    public static function populateComponentsArrayWithCollectedClassReferences(array &$target): array
    {
        foreach (static::$collectedClassReferences as $key => $component) {
            $keyDotted = str_replace('/', '.', mb_substr($key, 13)); // 13 = length of `#/components/`
            Arr::set($target, $keyDotted, $component);
        }

        return static::$collectedClassReferences;
    }

    /**
     * Get reference if it was collected for given class.
     *
     * @param  string $className
     * @param  array  $with
     * @param  array  $except
     * @param  array  $only
     * @return array|null
     */
    protected static function getCollectedClassReference(string $className, array $with = [], array $except = [], array $only = []): ?array
    {
        if (! static::$collectClassRefs) {
            return null;
        }
        $refPath = static::getCollectedClassReferenceName($className, $with, $except, $only);
        if (isset(static::$collectedClassReferences[$refPath])) {
            return ['$ref' => $refPath];
        }

        return null;
    }

    /**
     * Save class data for reference.
     *
     * @param  string $className
     * @param  array  $classDescribed
     * @param  array  $with
     * @param  array  $except
     * @param  array  $only
     * @param  bool   $returnRef
     * @return array
     */
    protected static function setCollectedClassReference(string $className, array $classDescribed, array $with = [], array $except = [], array $only = [], bool $returnRef = true): array
    {
        if (! static::$collectClassRefs) {
            return $classDescribed;
        }
        $refPath = static::getCollectedClassReferenceName($className, $with, $except, $only);
        static::$collectedClassReferences[$refPath] = $classDescribed;

        return $returnRef ? ['$ref' => $refPath] : $classDescribed;
    }

    /**
     * Compose a key for reference.
     *
     * @param  string $className
     * @param  array  $with
     * @param  array  $except
     * @param  array  $only
     * @return string
     */
    private static function getCollectedClassReferenceName(string $className, array $with = [], array $except = [], array $only = []): string
    {
        $className = trim($className, " \t\n\r\0\x0B\\");
        sort($with);
        sort($except);
        sort($only);
        $classNameWithAttributes = $className
            . (! empty($with) ? '__w_'. implode('_', $with) : '')
            . (! empty($except) ? '__wo_' . implode('_', $except) : '')
            . (! empty($only) ? '__o_' . implode('_', $only) : '');
        if (isset(static::$collectedClassReferencesKeys[$classNameWithAttributes])) {
            return static::$collectedClassReferencesKeys[$classNameWithAttributes];
        }
        $classNameSafe = str_replace(['\\', '.'], '_', $classNameWithAttributes);
        $keys = ['components', RoutesParser::COMPONENT_OBJECTS, $classNameSafe];

        return static::$collectedClassReferencesKeys[$classNameWithAttributes] = '#/' . implode('/', $keys);
    }

}
