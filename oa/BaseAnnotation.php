<?php

namespace OA;

use Illuminate\Support\Arr;

/**
 * Basic annotation class to extend
 */
abstract class BaseAnnotation
{
    /**
     * BaseAnnotation constructor.
     * @param array $values
     */
    public function __construct($values)
    {
        $this->configureSelf($values);
    }

    /**
     * Dumps object data as array
     * @return array
     */
    public function toArray()
    {
        $reflection = new \ReflectionClass($this);
        $data = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $data[$property->name] = $this->{$property->name};
        }
        return $data;
    }

    /**
     * Configure object
     * @param array       $config
     * @param string|null $defaultParam
     * @return array
     */
    protected function configureSelf($config, $defaultParam = null)
    {
        $setParams = [];
        if (array_key_exists('value', $config) && !property_exists($this, 'value') && $defaultParam !== null) {
            $this->{$defaultParam} = Arr::pull($config, 'value');
            $setParams[] = $defaultParam;
        }
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
                $setParams[] = $key;
            }
        }
        return $setParams;
    }

    /**
     * Get object string representation
     * @return string
     */
    abstract public function __toString();
}
