<?php

namespace DigitSoft\Swagger\Yaml;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Parsers\ClassParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithVariableDescriber;

class Variable
{
    const KEY_EXAMPLE = 'example';
    const KEY_TYPE = 'type';
    const KEY_DESC = 'description';
    const KEY_NAME = 'name';
    const KEY_WITH = 'with';
    const KEY_EXCEPT = 'except';
    const KEY_ONLY = 'only';

    const SW_TYPE_STRING = 'string';
    const SW_TYPE_INTEGER = 'integer';
    const SW_TYPE_NUMBER = 'number';
    const SW_TYPE_BOOLEAN = 'boolean';
    const SW_TYPE_ARRAY = 'array';
    const SW_TYPE_OBJECT = 'object';

    const SW_FORMAT_INT32 = 'int32';
    const SW_FORMAT_INT64 = 'int64';
    const SW_FORMAT_FLOAT = 'float';
    const SW_FORMAT_BYTE = 'byte';
    const SW_FORMAT_DATE = 'date';
    const SW_FORMAT_DATETIME = 'date-time';
    const SW_FORMAT_PASSWORD = 'password';
    const SW_FORMAT_BINARY = 'binary';

    public $example;

    public $description;

    public $type;

    public $items;

    public $name;

    public $with = [];

    public $except = [];

    public $only = [];

    protected $descriptionsNested = [];

    protected $described;

    protected $parsedClassData = [];

    protected $fillable = [
        self::KEY_NAME,
        self::KEY_DESC,
        self::KEY_TYPE,
        self::KEY_WITH,
        self::KEY_EXCEPT,
        self::KEY_ONLY,
    ];

    protected $swaggerType;

    protected static $_cache_objects = [];

    use WithReflections, WithAnnotationReader, WithVariableDescriber;

    /**
     * Variable constructor.
     * @param  array $config
     */
    public function __construct($config = [])
    {
        $this->configureSelf($config);
    }

    /**
     * Get object array representation
     * @return array
     */
    public function toArray()
    {
        $result = [];
        $this->fillMissingProperties();
        foreach ($this->getFillable() as $paramName) {
            $value = $this->{$paramName};
            if ($value === null && ! in_array($paramName, [static::KEY_TYPE], true)) {
                continue;
            }
            $result[$paramName] = $value;
        }

        return $result;
    }

    /**
     * Get cache key for objects.
     *
     * @param  string $className
     * @return string|null
     */
    protected function generateObjectCacheKey($className)
    {
        if (! class_exists($className) && ! interface_exists($className)) {
            return null;
        }

        return $className
            . '::' . (! empty($this->with) ? implode(',', $this->with) : '-')
            . '::' . (! empty($this->except) ? implode(',', $this->except) : '-')
            . '::' . (! empty($this->only) ? implode(',', $this->only) : '-');
    }

    /**
     * Describe variable
     * @return array
     */
    public function describe()
    {
        $this->fillMissingProperties();
        $result = [
            'type' => $this->getSwType(),
        ];
        if ($this->description !== null) {
            $result['description'] = trim($this->description);
        }
        if ($this->example !== null) {
            $result['example'] = $this->example;
        }
        $res = [];
        switch ($result['type']) {
            case static::SW_TYPE_OBJECT:
                $className = $this->describer()->normalizeType($this->type);
                $objCacheKey = $this->generateObjectCacheKey($className);
                $res = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
                // If class does not exists then $objCacheKey will be NULL
                if ($objCacheKey !== null) {
                    // Look for a cache
                    if (isset(static::$_cache_objects[$objCacheKey])) {
                        $cached = static::$_cache_objects[$objCacheKey];
                        if ($this->description) {
                            $cached['description'] = $this->description;
                        }
                        return $cached;
                    }
                    $res['properties'] = $this->getDescriptionByPHPDocTypeClass($className, $this->with);
                    $res['properties'] = $res['properties'] ?? [];
                } elseif (is_array($this->example) && Arr::isAssoc($this->example)) {
                    $describedEx = $this->describer()->describe($this->example);
                    $res['properties'] = Arr::get($describedEx, 'properties', []);
                    // Remove already described example
                    Arr::forget($result, 'example');
                }
                $res['properties'] = ! empty($this->except) ? Arr::except($res['properties'], $this->except) : $res['properties'];
                break;
            case static::SW_TYPE_ARRAY:
                if ($this->describer()->isTypeArray($this->type)) {
                    $simpleType = $this->describer()->normalizeType($this->type, true);
                    $item = $this->describer()->isBasicType($simpleType)
                        ? ['type' => $simpleType]
                        : (new static(['type' => $simpleType]))->describe();
                } else {
                    $thatItems = $this->items ?? 'string';
                    $item = is_array($thatItems) ? $thatItems : ['type' => $thatItems];
                }
                $res = [
                    'items' => $item,
                ];
                break;
        }
        $result = $this->describer()->merge($res, $result);
        // Write object data to cache
        if (isset($objCacheKey) && $result['type'] === static::SW_TYPE_OBJECT) {
            static::$_cache_objects[$objCacheKey] = $result;
        }

        return $result;
    }

    /**
     * Describe self as a PHP class.
     *
     * @return array
     * @throws \Throwable
     */
    protected function describeAsClass()
    {
        $className = $this->describer()->normalizeType($this->type);
        $result = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
        if (class_exists($className)) {
            $result['properties'] = $this->getDescriptionByPHPDocTypeClass($className, $this->with);
            $result['properties'] = ! empty($this->except) ? Arr::except($result['properties'], $this->except) : $result['properties'];
        }

        return $result;
    }

    /**
     * Get swagger type.
     *
     * @return string|null
     */
    protected function getSwType()
    {
        if ($this->swaggerType === null) {
            $phpType = $this->type !== null
                ? $this->describer()->normalizeType($this->type)
                : $this->getPHPDocType($this->example);
            $simplifiedType = $this->describer()->isTypeClassName($phpType) ? $this->describer()->simplifyClassName($phpType) : $phpType;
            if ($phpType === 'array' && is_array($this->example) && Arr::isAssoc($this->example)) {
                $swType = static::SW_TYPE_OBJECT;
            } elseif ($this->describer()->isTypeArray($phpType)) {
                $swType = static::SW_TYPE_ARRAY;
            } elseif ($this->describer()->isTypeClassName($phpType)) {
                $swType = $simplifiedType === $phpType
                    ? static::SW_TYPE_OBJECT
                    : $simplifiedType;
            } else {
                $swType = $this->describer()->swaggerType($phpType);
            }

            return $this->swaggerType = $swType;
        }

        return $this->swaggerType;
    }

    /**
     * Fill missing properties.
     */
    protected function fillMissingProperties()
    {
        $type = $this->type !== null && $this->describer()->isTypeClassName($this->type) ? $this->describer()->simplifyClassName($this->type) : $this->type;
        if ($this->type === null && $this->example !== null) {
            $this->type = $this->getPHPDocType($this->example);
        } elseif ($this->type !== null && $this->example === null && $this->describer()->isBasicType($type)) {
            $this->example = $this->getExampleByPHPDocType($this->type);
        }
    }

    /**
     * Get PHPDoc type of value.
     *
     * @param  mixed $value
     * @return string|null
     */
    protected function getPHPDocType($value)
    {
        $baseType = $phpType = $value !== null ? gettype($value) : null;
        switch ($baseType) {
            case 'array':
                if (! Arr::isAssoc($value)) {
                    $firstValue = reset($value);
                    if (($mainType = $this->getPHPDocType($firstValue)) !== null) {
                        $phpType = $mainType . '[]';
                    }
                }
                break;
            case 'object':
                $phpType = get_class($value);
                break;
        }

        return $phpType;
    }

    /**
     * Get example value by PHPDoc type.
     *
     * @param  string $phpType
     * @return array|mixed|null
     */
    protected function getExampleByPHPDocType($phpType)
    {
        $phpType = $this->describer()->normalizeType($phpType);
        $phpTypeSimplified = $this->describer()->simplifyClassName($phpType);
        if (($isArrayOf = $this->describer()->isTypeArray($phpType)) !== false) {
            $phpType = substr($phpType, 0, -2);
        }
        if ($phpTypeSimplified === $phpType && $this->describer()->isTypeClassName($phpType)) {
            $example = $this->getExampleByPHPDocTypeClass($phpType);
            $example = $example ?? [];
        } else {
            $example = $this->describer()->example($phpType, null, $this->name);
        }
        if ($isArrayOf) {
            $example = [$example];
        }

        return $example;
    }

    /**
     * Get example by class name.
     *
     * @param  string $className
     * @return array|null
     */
    protected function getExampleByPHPDocTypeClass($className)
    {
        $parser = new ClassParser($className);
        $properties = $parser->properties(true, false);
        if (! empty($this->with)) {
            $propertiesRead = $parser->propertiesRead($this->with);
            $properties = $this->describer()->merge($properties, $propertiesRead);
        }
        if (empty($properties)) {
            return null;
        }
        $example = [];
        foreach ($properties as $name => $row) {
            $ex = $this->describer()->example($row['type']);
            $example[$name] = $ex;
        }

        return $example;
    }

    /**
     * Get description by class name.
     *
     * @param  string $className
     * @param  array  $with
     * @return array|null
     */
    protected function getDescriptionByPHPDocTypeClass($className, $with = [])
    {
        $parser = new ClassParser($className);
        $properties = $parser->properties(true, false);
        if (! empty($with)) {
            $this->setWithToPropertiesRecursively($properties, $with);
            $propertiesRead = $parser->propertiesRead($with, null, false);
            $propertiesByAnnRead = $this->getDescriptionByPropertyAnnotations($className, $with, 'OA\PropertyRead');
            $properties = $this->describer()->merge($properties, $propertiesRead, $propertiesByAnnRead);
        }
        $propertiesByAnn = $this->getDescriptionByPropertyAnnotations($className);
        $properties = $this->describer()->merge($properties, $propertiesByAnn);
        if (empty($properties)) {
            return null;
        }
        // Ignore properties
        $ignored = $this->getIgnoredProperties($className);
        if (! empty($ignored)) {
            $properties = array_diff_key($properties, $ignored);
        }
        $described = [];
        foreach ($properties as $name => $row) {
            if (isset($propertiesByAnn[$name])) {
                $described[$name] = Arr::except($row, ['name']);
                continue;
            }
            $row = $this->describer()->merge(['name' => $name], $row);
            $nested = static::fromDescription($row);
            try {
                $described[$name] = $nested->describe();
            } catch (\Throwable $exception) {
                dump($row);
                throw $exception;
            }
        }

        return $described;
    }

    /**
     * Get properties by annotations
     *
     * @param  string $className
     * @param  array  $only
     * @param  string $annotationClass
     * @return array
     */
    protected function getDescriptionByPropertyAnnotations($className, $only = [], $annotationClass = 'OA\Property')
    {
        /** @var \OA\Property[] $annotations */
        $annotations = $this->classAnnotations($className, $annotationClass);
        $result = [];
        foreach ($annotations as $annotation) {
            $rowData = $annotation->toArray();
            if (! empty($only) && ! in_array($annotation->name, $only, true)) {
                continue;
            }
            // Skip annotations w/o name
            if (empty($annotation->name)) {
                continue;
            }
            // Handle nested names (with dots)
            if (
                $annotation->isNested()
                && ([$nestedPath, $nestedParentPath, $nestedName] = $annotation->getNestedPaths())
                && $nestedParentPath !== null
                && Arr::get($result, $nestedParentPath . '.type') === static::SW_TYPE_OBJECT
            ) {
                $rowData['name'] = $nestedName;
                Arr::set($result, $nestedPath, $rowData);
                continue;
            }
            $result[$annotation->name] = $rowData;
        }

        return $result;
    }

    /**
     * Get ignored properties
     * @param  string $className
     * @return array
     */
    protected function getIgnoredProperties($className)
    {
        /** @var \OA\PropertyIgnore[] $annotations */
        $annotations = $this->classAnnotations($className, 'OA\PropertyIgnore');

        return Arr::pluck($annotations, 'name', 'name');
    }

    /**
     * Set `with` key to describable properties recursively.
     *
     * @param  array $properties
     * @param  array $with
     */
    protected function setWithToPropertiesRecursively(array &$properties, $with = [])
    {
        foreach ($with as $key) {
            if (($pos  = strpos($key, '.')) === false) {
                continue;
            }
            if (($keyBase = mb_substr($key, 0, $pos)) && isset($properties, $keyBase)) {
                $keyWith = mb_substr($key, $pos + 1);
                $properties[$keyBase]['with'] = [$keyWith];
            }
        }
    }

    /**
     * Configure object itself
     * @param  array $config
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
     * Get fillable properties
     * @return array
     */
    public function getFillable()
    {
        return $this->fillable;
    }

    /**
     * Getter
     * @param  string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        throw new \Exception("Property {$name} does not exist or is not readable");
    }

    /**
     * Setter
     * @param  string $name
     * @param  mixed  $value
     * @return mixed
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}($value);
        }
        throw new \Exception("Property {$name} does not exist or is not writable");
    }

    /**
     * ISSET magic.
     *
     * @param  string $name
     * @return bool
     */
    public function __isset($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}() !== null;
        }

        return false;
    }

    /**
     * Create object from array description
     * @param  array $config
     * @return Variable
     */
    public static function fromDescription($config)
    {
        return new static($config);
    }

    /**
     * Create object from example
     * @param  mixed       $example
     * @param  string|null $name
     * @param  string|null $description
     * @return Variable
     */
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
