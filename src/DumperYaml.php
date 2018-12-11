<?php

namespace DigitSoft\Swagger;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

class DumperYaml
{
    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var \ReflectionClass[]
     */
    protected static $reflections = [];

    protected static $faker;

    /**
     * DumperYaml constructor.
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Export YAML data to file
     * @param array  $content
     * @param string $filePath
     * @param bool   $describe
     * @return string
     */
    public function toYml($content = [], $describe = false, $filePath = null)
    {
        $arrayContent = $content;
        if ($describe) {
            $arrayContent = static::describe($arrayContent);
        }
        $yamlContent = Yaml::dump($arrayContent, 20, 2);
        if ($filePath !== null) {
            $this->files->put($filePath, $yamlContent);
        }
        return $yamlContent;
    }

    /**
     * Describe variable
     * @param mixed $variable
     * @param bool $withExample
     * @return array
     */
    public static function describe($variable, $withExample = true)
    {
        return static::describeValue($variable, $withExample);
    }

    /**
     * Get example value
     * @param string $type
     * @return mixed
     */
    public static function getExampleValue(string $type)
    {
        switch ($type) {
            case 'integer':
                return static::faker()->numberBetween(1, 99);
                break;
            case 'float':
                return static::faker()->randomFloat(2);
                break;
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
        }
        return null;
    }

    /**
     * Get example value by validation rule
     * @param string $rule
     * @return mixed
     */
    public static function getExampleValueByRule(string $rule)
    {
        switch ($rule) {
            case 'url':
                return static::faker()->url;
                break;
            case 'alpha':
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
            case 'alpha_num':
                return array_random(['string35', 'value90', 'str20value']);
                break;
            case 'alpha_dash':
                return array_random(['string_35', 'value-90', 'str_20-value']);
                break;
            case 'ip':
            case 'ipv4':
                return static::faker()->ipv4;
                break;
            case 'ipv6':
                return static::faker()->ipv6;
                break;
            case 'float':
                return static::faker()->randomFloat(2);
                break;
            case 'date':
                return static::faker()->date();
                break;
            case 'numeric':
            case 'integer':
                return static::faker()->numberBetween(1, 99);
                break;
            case 'boolean':
                return static::faker()->boolean;
                break;
        }
        return null;
    }

    /**
     * Get faker instance
     * @return Generator
     */
    protected static function faker()
    {
        if (static::$faker === null) {
            static::$faker = Factory::create();
        }
        return static::$faker;
    }

    /**
     * Describe one value
     * @param  mixed $value
     * @param  bool  $withExample
     * @return array
     */
    protected static function describeValue($value, $withExample = true)
    {
        $type = gettype($value);
        $desc = ['type' => $type];
        $examplable = ['string', 'integer', 'float', 'boolean'];
        switch ($type) {
            case 'object':
                $desc = static::describeObject($value);
                break;
            case 'array':
                $desc = static::describeArray($value);
                break;
        }
        if ($withExample && in_array($type, $examplable)) {
            $desc['example'] = $value;
        }
        return $desc;
    }

    /**
     * Describe object
     * @param object $value
     * @return array
     */
    protected static function describeObject($value)
    {
        $data = [
            'type' => 'object',
            'properties' => [],
        ];
        if (method_exists($value, 'toArray')) {
            $objProperties = $value->toArray();
        } else {
            $reflection = static::reflection($value);
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
            $data['properties'][$key] = static::describeValue($val);
        }
        return $data;
    }

    /**
     * Describe array
     * @param array $value
     * @return array
     */
    protected static function describeArray($value)
    {
        $isAssoc = Arr::isAssoc($value);
        if ($isAssoc) {
            $data = [
                'type' => 'object',
                'properties' => [],
            ];
            foreach ($value as $key => $val) {
                $data['properties'][$key] = static::describeValue($val);
            }
        } else {
            $data = [
                'type' => 'array',
                'items' => static::describeValue(reset($value)),
            ];
        }
        return $data;
    }

    /**
     * Get object reflection
     * @param string|object $class
     * @return \ReflectionClass
     */
    protected static function reflection($class)
    {
        $className = ltrim(is_string($class) ? $class : get_class($class), '\\');
        if (!isset(static::$reflections[$className])) {
            static::$reflections[$className] = new \ReflectionClass($className);
        }
        return static::$reflections[$className];
    }
}
