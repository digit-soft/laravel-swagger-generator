<?php

namespace DigitSoft\Swagger\Yaml;

use Illuminate\Contracts\Support\Arrayable;

class Component implements Arrayable
{
    protected static $_reflection;

    /**
     * Component constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->fill($config);
    }

    /**
     * Fill class with data
     * @param array $config
     */
    public function fill(array $config)
    {
        foreach ($config as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $property = $this->reflection()->getProperty($key);
            if ($property->isPrivate() || $property->isStatic()) {
                continue;
            }
            $this->{$key} = $value;
        }
    }

    /**
     * Get class reflection
     * @return \ReflectionClass
     */
    protected function reflection()
    {
        if (self::$_reflection === null) {
            self::$_reflection = new \ReflectionClass($this);
        }
        return self::$_reflection;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [];
    }
}
