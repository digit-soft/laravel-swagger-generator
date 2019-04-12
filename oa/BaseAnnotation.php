<?php

namespace OA;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Basic annotation class to extend
 */
abstract class BaseAnnotation implements Arrayable
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
     * Load data into object
     * @param array $data
     * @return static
     */
    public function fill(array $data)
    {
        $this->configureSelf($data);
        return $this;
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
     * Magic getter
     * @param  string $name
     * @return mixed
     * @throws \ErrorException
     */
    public function __get($name)
    {
        $getter = 'get' . ucfirst(Str::camel($name));
        if (!method_exists($this, $getter)) {
            throw new \ErrorException("Undefined property: " . __CLASS__ . "::\${$name}");
        }
        return $this->{$getter}();
    }

    /**
     * Get object string representation
     * @return string
     */
    abstract public function __toString();
}
