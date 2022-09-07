<?php

namespace OA;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Basic annotation class to extend
 */
abstract class BaseAnnotation implements Arrayable
{
    const NULL_VALUE = 'NULL';

    /**
     * BaseAnnotation constructor.
     *
     * @param  array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values);
    }

    /**
     * Dumps object data as array
     *
     * @return array
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $data = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || ! $property->isInitialized($this)) {
                continue;
            }
            $value = $this->{$property->name};
            $data[$property->name] = $value !== static::NULL_VALUE ? $value : null;
        }

        return $data;
    }

    /**
     * Load data into object
     *
     * @param  array $data
     * @return static
     */
    public function fill(array $data): static
    {
        $this->configureSelf($data);

        return $this;
    }

    /**
     * Configure object
     *
     * @param  array       $config
     * @param  string|null $defaultParam
     * @return array
     */
    protected function configureSelf(array $config, ?string $defaultParam = null): array
    {
        $setParams = [];
        if (array_key_exists('value', $config) && ! property_exists($this, 'value') && $defaultParam !== null) {
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
     *
     * @param  string $name
     * @return mixed
     * @throws \ErrorException
     */
    public function __get(string $name)
    {
        $getter = 'get' . Str::studly($name);
        if (! method_exists($this, $getter)) {
            throw new \ErrorException("Undefined property: " . __CLASS__ . "::\${$name}");
        }

        return $this->{$getter}();
    }

    /**
     * Magic setter.
     *
     * @param  string $name
     * @param  mixed  $value
     * @throws \ErrorException
     */
    public function __set(string $name, mixed $value): void
    {
        $setter = 'set' . Str::studly($name);
        if (! method_exists($this, $setter)) {
            throw new \ErrorException("Undefined property: " . __CLASS__ . "::\${$name}");
        }
        $this->{$setter}($value);
    }

    /**
     * Magic `isset`.
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $getter = 'get' . Str::studly($name);

        return method_exists($this, $getter) && $this->{$getter}() !== null;
    }

    /**
     * Get object string representation
     *
     * @return string
     */
    abstract public function __toString();
}
