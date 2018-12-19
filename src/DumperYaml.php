<?php

namespace DigitSoft\Swagger;

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
    /**
     * @var \ReflectionClass[]
     */
    protected static $reflections = [];
    /**
     * @var Model[]
     */
    protected static $_models;

    protected static $faker;

    protected static $basicTypes = [
        'string', 'integer', 'float', 'object', 'boolean', 'null', 'array', 'resource',
    ];

    protected static $basicTypesShort = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];

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
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     */
    public static function getExampleValue(string $type, $varName = null)
    {
        $isArray = strpos($type, '[]') !== false;
        $type = $isArray ? substr($type, 0, -2) : $type;
        if (($example = static::getExampleValueInternal($type, $varName)) === null) {
            return null;
        }
        return $isArray ? [$example] : $example;
    }

    /**
     * Get example value
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     */
    protected static function getExampleValueInternal(string $type, $varName = null)
    {
        if (strpos($type, '\\') === 0) {
            $type = substr($type, 1);
        }
        $generalTypes = ['string', 'mixed', 'null'];
        if (in_array($type, $generalTypes) && $varName !== null && ($typeByName = static::getExampleValueByName($varName)) !== null) {
            return $typeByName;
        }
        switch ($type) {
            case 'int':
            case 'integer':
                return static::faker()->numberBetween(1, 99);
                break;
            case 'float':
            case 'double':
                return static::faker()->randomFloat(2);
                break;
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
            case 'bool':
            case 'boolean':
                return static::faker()->boolean;
                break;
            case 'date':
                return static::faker()->dateTimeBetween('-1 month')->format('Y-m-d');
                break;
            case 'Illuminate\Support\Carbon':
            case 'dateTime':
                return static::faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'array':
                return [];
                break;
        }
        return null;
    }

    /**
     * Get example value by it`s name
     * @param string $name
     * @return mixed|null
     */
    protected static function getExampleValueByName(string $name)
    {
        $subTypes = [
            'url' => [
                'url',
            ],
            'email' => [
                'email',
                'mail',
            ],
            'password' => [
                'password',
                'pass',
                'remember_token',
                'email_token',
            ],
            'token' => [
                'token',
                'email_token',
                'remember_token',
                'service_token',
            ],
            'domain_name' => [
                'domain',
                'domainName',
            ],
            'service_name' => [
                'service_name',
                'serviceName',
            ],
            'phone' => [
                'phone',
                'phone_number',
                'phone_numbers',
                'phones',
            ],
        ];
        foreach ($subTypes as $subType => $names) {
            if (in_array($name, $names)) {
                return static::getExampleValueByRule($subType);
            }
        }
        return null;
    }

    /**
     * Get example value by validation rule
     * @param string      $rule
     * @param string|null $varName
     * @return mixed
     */
    public static function getExampleValueByRule(string $rule, $varName = null)
    {
        $generalTypes = ['string'];
        if (in_array($rule, $generalTypes) && $varName !== null && ($typeByName = static::getExampleValueByName($varName)) !== null) {
            return $typeByName;
        }
        switch ($rule) {
            case 'phone':
                return static::faker()->phoneNumber;
                break;
            case 'url':
                return static::faker()->url;
                break;
            case 'email':
                return static::faker()->email;
                break;
            case 'password':
                return static::faker()->password(16, 36);
                break;
            case 'token':
                return str_random(64);
                break;
            case 'service_name':
                return array_random(['fb', 'google', 'twitter']);
                break;
            case 'domain_name':
                return static::faker()->domainName;
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
     * Check that given type is basic
     * @param string $type
     * @return bool
     */
    public static function isBasicType($type)
    {
        $type = static::normalizeType($type);
        return in_array($type, static::$basicTypes);
    }

    /**
     * Check that given type is class name
     * @param string $type
     * @return bool
     */
    public static function isTypeClassName($type)
    {
        $type = static::normalizeType($type);
        return !in_array($type, static::$basicTypes) && class_exists($type);
    }

    /**
     * Normalize type name
     * @param string $type
     * @return string
     */
    public static function normalizeType($type)
    {
        $typeLower = strtolower($type);
        if (isset(static::$basicTypesShort[$typeLower])) {
            return static::$basicTypesShort[$typeLower];
        }
        if (strpos($type, '\\') !== false) {
            return ltrim($type, '\\');
        }
        return $typeLower;
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
