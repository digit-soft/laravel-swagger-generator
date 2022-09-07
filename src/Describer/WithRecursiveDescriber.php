<?php

namespace DigitSoft\Swagger\Describer;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;
use DigitSoft\Swagger\Parser\WithReflections;

/**
 * Trait WithRecursiveDescriber
 *
 * @mixin WithReflections
 * @mixin WithTypeParser
 */
trait WithRecursiveDescriber
{
    /**
     * Describe one value
     *
     * @param  mixed $value
     * @param  array $additionalData
     * @param  bool  $withExample
     * @return array
     */
    protected function describeValue(mixed $value, array $additionalData = [], bool $withExample = true): array
    {
        $swaggerType = $this->swaggerType(strtolower(gettype($value)));
        $swaggerType = $swaggerType === 'null' ? null : $swaggerType;
        $desc = ['type' => $swaggerType];
        $couldHaveExamples = [
            Variable::SW_TYPE_STRING,
            Variable::SW_TYPE_INTEGER,
            Variable::SW_TYPE_NUMBER,
            Variable::SW_TYPE_BOOLEAN,
        ];
        switch ($swaggerType) {
            case Variable::SW_TYPE_OBJECT:
                $desc = $this->describeObject($value, $additionalData);
                break;
            case Variable::SW_TYPE_ARRAY:
                $desc = $this->describeArray($value, $additionalData, $withExample);
                break;
        }
        if ($withExample && in_array($swaggerType, $couldHaveExamples, true)) {
            $desc['example'] = $value;
            if (! empty($additionalData)) {
                $desc = array_merge($desc, $additionalData);
            }
        }

        return $desc;
    }

    /**
     * Describe object
     *
     * @param  object $value
     * @param  array  $additionalData
     * @param  bool   $withExample
     * @return array
     */
    protected function describeObject(object $value, array $additionalData = [], bool $withExample = true): array
    {
        $data = [
            'type' => 'object',
            'properties' => [],
        ];
        if (method_exists($value, 'toArray')) {
            $objProperties = app()->call([$value, 'toArray'], ['request' => request()]);
        } else {
            $reflection = $this->reflectionClass($value);
            $refProperties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            $objProperties = [];
            foreach ($refProperties as $refProperty) {
                if ($refProperty->isStatic()) {
                    continue;
                }
                $name = $refProperty->getName();
                $objProperties[$name] = $value->{$name};
            }
        }
        foreach ($objProperties as $key => $val) {
            $data['properties'][$key] = $this->describeValue($val, $additionalData[$key] ?? [], $withExample);
        }

        return $data;
    }

    /**
     * Describe array
     *
     * @param  array $value
     * @param  array $additionalData
     * @param  bool  $withExample
     * @return array
     */
    protected function describeArray(array $value, array $additionalData = [], bool $withExample = true): array
    {
        if (empty($value)) {
            $data = [
                'type' => 'object',
            ];
        } elseif (Arr::isAssoc($value)) {
            $data = [
                'type' => 'object',
                'properties' => [],
            ];
            foreach ($value as $key => $val) {
                $data['properties'][$key] = $this->describeValue($val, $additionalData[$key] ?? [], $withExample);
            }
        } else {
            $additionalDataRow = $additionalData[0] ?? [];
            $data = [
                'type' => 'array',
                'items' => $this->describeValue(reset($value), $additionalDataRow),
            ];
        }

        return $data;
    }
}
