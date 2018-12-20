<?php

namespace DigitSoft\Swagger\Yaml;


use Illuminate\Support\Arr;

class Variable
{
    const KEY_EXAMPLE = 'example';
    const KEY_TYPE = 'type';
    const KEY_DESC = 'description';
    const KEY_NAME = 'name';

    public $example;

    public $description;

    public $type;

    public $name;
    
    protected $described;

    /**
     * Variable constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->configureSelf($config);
    }

    /**
     * Configure object itself
     * @param array $config
     */
    protected function configureSelf($config = [])
    {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Get object array representation
     * @return array
     */
    public function toArray()
    {
        $params = [static::KEY_EXAMPLE, static::KEY_NAME, static::KEY_DESC, static::KEY_TYPE];
        $result = [];
        foreach ($params as $paramName) {
            $value = $this->{$paramName};
            if ($value === null && !in_array($paramName, [static::KEY_TYPE])) {
                continue;
            }
            $result[$paramName] = $value;
        }
        return $result;
    }

    /**
     * Getter
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }
        throw new \Exception("Property {$name} does not exist or is not readable");
    }

    /**
     * Setter
     * @param string $name
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $value);
        }
        throw new \Exception("Property {$name} does not exist or is not writable");
    }

    public static function fromDescription($description)
    {
        $config = Arr::only($description, ['example', 'type', 'description', 'name']);
        return new static($config);
    }

    public static function fromExample($example, $name = null, $description = null)
    {
        $config = [
            'example' => $example,
            'name' => $name,
            'description' => $description,
        ];
        return new static($config);
    }
}
