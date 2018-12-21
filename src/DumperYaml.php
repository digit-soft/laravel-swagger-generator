<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\DescribesVariables;
use DigitSoft\Swagger\Parser\WithFaker;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

class DumperYaml
{
    /**
     * @var Filesystem
     */
    protected $files;

    protected static $classShortcuts = [];
    /**
     * @var \ReflectionClass[]
     */
    protected static $reflections = [];
    /**
     * @var Model[]
     */
    protected static $_models;

    protected static $basicTypes = [
        'string', 'integer', 'float', 'object', 'boolean', 'null', 'array', 'resource',
    ];

    protected static $basicTypesSyn = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];

    protected static $classSimpleTypes = [
        'Illuminate\Support\Carbon' => 'string',
    ];

    use WithFaker, DescribesVariables;

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
     * Parse Yaml file
     * @param string $filePath
     * @return array
     */
    public function fromYml($filePath)
    {
        $contentStr = $this->files->get($filePath);
        $content = Yaml::parse($contentStr);
        return $content;
    }

    /**
     * Shorten class name
     * @param string $className
     * @return string
     */
    public static function shortenClass($className)
    {
        $className = ltrim($className, '\\');
        if (isset(static::$classShortcuts[$className])) {
            return static::$classShortcuts[$className];
        }
        $classNameArray = explode('\\', $className);
        $classNameShort = $classNameShortBase = end($classNameArray);
        $num = 0;
        while (in_array($classNameShort, static::$classShortcuts)) {
            $classNameShort = $classNameShortBase . '_' . $num;
            $num++;
        }
        return static::$classShortcuts[$className] = $classNameShort;
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
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     */
    public static function getExampleValue(string $type, $varName = null)
    {
        return static::exampleValue($type, $varName);
    }

    /**
     * Get example value by validation rule
     * @param string      $rule
     * @param string|null $varName
     * @return mixed
     */
    public static function getExampleValueByRule(string $rule, $varName = null)
    {
        return static::exampleByRule($rule, $varName);
    }

    /**
     * Check that given type is basic
     * @param string $type
     * @return bool
     */
    public static function isBasicType($type)
    {
        $type = static::normalizeType($type, true);
        return in_array($type, static::$basicTypes);
    }

    /**
     * Check that given type is class name
     * @param string $type
     * @return bool
     */
    public static function isTypeClassName($type)
    {
        $type = static::normalizeType($type, true);
        return !in_array($type, static::$basicTypes) && class_exists($type);
    }

    /**
     * Check that given type is array of types
     * @param string $type
     * @return bool
     */
    public static function isTypeArray($type)
    {
        $type = static::normalizeType($type);
        return strpos($type, '[]') !== false;
    }

    /**
     * Normalize type name
     * @param string $type
     * @param bool   $stripArray
     * @return string
     */
    public static function normalizeType($type, $stripArray = false)
    {
        $type = strpos($type, '|') ? explode('|', $type)[0] : $type;
        if ($stripArray && static::isTypeArray($type)) {
            $type = substr($type, 0, -2);
        }
        $typeLower = strtolower($type);
        if (isset(static::$basicTypesSyn[$typeLower])) {
            return static::$basicTypesSyn[$typeLower];
        }
        if (strpos($type, '\\') !== false) {
            return ltrim($type, '\\');
        }
        return $typeLower;
    }

    /**
     * Simplify class name to basic type
     * @param string $className
     * @return mixed|string
     */
    public static function simplifyClassName($className)
    {
        $className = ltrim($className, '\\');
        return static::$classSimpleTypes[$className] ?? $className;
    }

    /**
     * Make eloquent model
     * @param string $className
     * @param bool   $create
     * @param array  $requiredRelations
     * @return Model|null
     * @internal
     */
    public static function makeModel($className, $create = true, $requiredRelations = [])
    {
        /** @var Builder $modelQuery */
        $modelQuery = $className::query();
        if (!empty($requiredRelations)) {
            foreach ($requiredRelations as $requiredRelation) {
                $modelQuery->whereHas($requiredRelation);
            }
        }
        /** @var Model $model */
        $model = $modelQuery->first();
        if ($model === null && $create) {
            $model = \factory($className)->create()->refresh();
            if (!empty($requiredRelations)) {
                foreach ($requiredRelations as $requiredRelation) {
                    static::fillModelRelation($model, $requiredRelation);
                }
            }
        }
        if ($model !== null) {
            static::$_models[$className] = $model;
        }
        return static::$_models[$className] ?? null;
    }

    /**
     * Merge arrays
     * @param array $a
     * @param array $b
     * @return array
     */
    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    protected static function fillModelRelation(Model $model, $relationName)
    {
        /** @var HasOneOrMany $relation */
        $relation = $model->{$relationName}();
        $attributes = \factory(get_class($relation->getRelated()))->make()->toArray();
        $relation->create($attributes);
        $model->refresh();
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
        $type = strtolower(gettype($value));
        $type = $type === 'null' ? null : $type;
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
            $objProperties = app()->call([$value, 'toArray'], ['request' => request()]);
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
