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
     * @param  bool  $withExample
     * @return array
     */
    protected function describeValue($value, bool $withExample = true)
    {
        $type = $this->swaggerType(strtolower(gettype($value)));
        $type = $type === 'null' ? null : $type;
        $desc = ['type' => $type];
        $examplable = [
            Variable::SW_TYPE_STRING,
            Variable::SW_TYPE_INTEGER,
            Variable::SW_TYPE_NUMBER,
            Variable::SW_TYPE_BOOLEAN,
        ];
        switch ($type) {
            case 'object':
                $desc = $this->describeObject($value);
                break;
            case 'array':
                $desc = $this->describeArray($value, $withExample);
                break;
        }
        if ($withExample && in_array($type, $examplable, true)) {
            $desc['example'] = $value;
        }

        return $desc;
    }

    /**
     * Describe object
     *
     * @param  object $value
     * @param  bool   $withExample
     * @return array
     */
    protected function describeObject($value, bool $withExample = true)
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
            $data['properties'][$key] = $this->describeValue($val, $withExample);
        }

        return $data;
    }

    /**
     * Describe array
     *
     * @param  array $value
     * @param  bool  $withExample
     * @return array
     */
    protected function describeArray($value, bool $withExample = true)
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
                $data['properties'][$key] = $this->describeValue($val, $withExample);
            }
        } else {
            $data = [
                'type' => 'array',
                'items' => $this->describeValue(reset($value)),
            ];
        }

        return $data;
    }
}
