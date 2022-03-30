<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;

/**
 * Trait CleanupsDescribedData
 */
trait CleanupsDescribedData
{
    /**
     * Remove incompatible array keys for current type
     *
     * @param  array $target
     */
    protected static function handleIncompatibleTypeKeys(array &$target)
    {
        foreach ($target as $key => &$value) {
            if (is_array($value)) {
                static::handleIncompatibleTypeKeys($value);
            }
        }
        unset($value);
        if (($type = $target['type'] ?? null) === null) {
            return;
        }
        // Make enums unique
        if (is_array($enum = $target['enum'] ?? null)) {
            $target['enum'] = array_values(array_unique($enum));
        }
        switch ($type) {
            case Variable::SW_TYPE_OBJECT:
                Arr::forget($target, ['items']);
                // if (!isset($target['properties']) && !isset($target['example'])) {
                //     $target['properties'] = [];
                // }
                break;
            case Variable::SW_TYPE_ARRAY:
                Arr::forget($target, ['properties']);
                if (isset($target['example']) && isset($target['items']['example'])) {
                    Arr::forget($target, ['items.example']);
                }
                break;
            default:
                Arr::forget($target, ['items', 'properties']);
        }
    }
}
