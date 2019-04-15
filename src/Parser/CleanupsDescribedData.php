<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Support\Arr;

/**
 * Trait CleanupsDescribedData
 * @package DigitSoft\Swagger\Parser
 */
trait CleanupsDescribedData
{
    /**
     * Remove incompatible array keys for current type
     * @param array $target
     */
    protected static function handleIncompatibleTypeKeys(array &$target)
    {
        foreach ($target as $key => &$value) {
            if (is_array($value)) {
                static::handleIncompatibleTypeKeys($value);
            }
        }
        if (!isset($target['type'])) {
            return;
        }
        $type = $target['type'];
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
