<?php

namespace DigitSoft\Swagger\Yaml;


use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\DescribesVariables;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithFaker;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parsers\ClassParser;
use Illuminate\Support\Arr;

class Variable
{
    const KEY_EXAMPLE = 'example';
    const KEY_TYPE = 'type';
    const KEY_DESC = 'description';
    const KEY_NAME = 'name';
    const KEY_WITH = 'with';
    const KEY_EXCEPT = 'except';
    const KEY_ONLY = 'only';

    const SW_TYPE_ARRAY = 'array';
    const SW_TYPE_OBJECT = 'object';

    public $example;

    public $description;

    public $type;

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

    use WithFaker, WithReflections, WithAnnotationReader, DescribesVariables;

    /**
     * Variable constructor.
     * @param array $config
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
            if ($value === null && !in_array($paramName, [static::KEY_TYPE])) {
                continue;
            }
            $result[$paramName] = $value;
        }
        return $result;
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
                $className = DumperYaml::normalizeType($this->type);
                $res = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
                if (class_exists($className)) {
                    $res['properties'] = $this->getDescriptionByPHPDocTypeClass($className, $this->with);
                    $res['properties'] = $res['properties'] ?? [];
                }
                break;
            case static::SW_TYPE_ARRAY:
                if (DumperYaml::isTypeArray($this->type)) {
                    $simpleType = DumperYaml::normalizeType($this->type, true);
                    $item = DumperYaml::isBasicType($simpleType)
                        ? $simpleType
                        : (new static(['type' => $simpleType]))->describe();
                } else {
                    $item = '';
                }
                $res = [
                    'items' => $item,
                ];
                break;
        }
        $result = DumperYaml::merge($res, $result);
        return $result;
    }

    protected function describeAsClass()
    {
        $className = DumperYaml::normalizeType($this->type);
        $result = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
        if (class_exists($className)) {
            $result['properties'] = $this->getDescriptionByPHPDocTypeClass($className, $this->with);
        }
        return $result;
    }

    /**
     * Get swagger type
     * @return string|null
     */
    protected function getSwType()
    {
        if ($this->swaggerType === null) {
            $phpType = $this->type !== null
                ? DumperYaml::normalizeType($this->type)
                : $this->getPHPDocType($this->example);
            $simplifiedType = DumperYaml::isTypeClassName($phpType) ? DumperYaml::simplifyClassName($phpType) : $phpType;
            if ($phpType === 'array' && is_array($this->example) && Arr::isAssoc($this->example)) {
                $swType = static::SW_TYPE_OBJECT;
            } elseif (DumperYaml::isTypeArray($phpType)) {
                $swType = static::SW_TYPE_ARRAY;
            } elseif (DumperYaml::isTypeClassName($phpType)) {
                $swType = $simplifiedType === $phpType
                    ? static::SW_TYPE_OBJECT
                    : $simplifiedType;
            } else {
                $swType = $phpType;
            }
            return $this->swaggerType = $swType;
        }
        return $this->swaggerType;
    }

    /**
     * Fill missing properties
     */
    protected function fillMissingProperties()
    {
        $type = $this->type !== null && DumperYaml::isTypeClassName($this->type) ? DumperYaml::simplifyClassName($this->type) : $this->type;
        if ($this->type === null && $this->example !== null) {
            $this->type = $this->getPHPDocType($this->example);
        } elseif ($this->type !== null && $this->example === null && DumperYaml::isBasicType($type)) {
            $this->example = $this->getExampleByPHPDocType($this->type);
        }
    }

    /**
     * Get PHPDoc type of value
     * @param mixed $value
     * @return string|null
     */
    protected function getPHPDocType($value)
    {
        $baseType = $phpType = !is_null($value) ? gettype($value) : null;
        switch ($baseType) {
            case 'array':
                if (!Arr::isAssoc($value)) {
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
     * Get example value by PHPDoc type
     * @param string $phpType
     * @return array|mixed|null
     */
    protected function getExampleByPHPDocType($phpType)
    {
        $phpType = DumperYaml::normalizeType($phpType);
        $phpTypeSimplified = DumperYaml::simplifyClassName($phpType);
        if (($isArrayOf = DumperYaml::isTypeArray($phpType)) !== false) {
            $phpType = substr($phpType, 0, -2);
        }
        if (DumperYaml::isTypeClassName($phpType) && $phpTypeSimplified === $phpType) {
            $example = $this->getExampleByPHPDocTypeClass($phpType);
            $example = $example ?? [];
        } else {
            $example = static::exampleValue($phpType, $this->name);
        }
        if ($isArrayOf) {
            $example = [$example];
        }
        return $example;
    }

    /**
     * Get example by class name
     * @param string $className
     * @return array|null
     */
    protected function getExampleByPHPDocTypeClass($className)
    {
        $parser = new ClassParser($className);
        $properties = $parser->properties(true, false);
        if (!empty($this->with)) {
            $propertiesRead = $parser->propertiesRead($this->with);
            $properties = DumperYaml::merge($properties, $propertiesRead);
        }
        if (empty($properties)) {
            return null;
        }
        $example = [];
        foreach ($properties as $name => $row) {
            $ex = static::exampleValue($row['type']);
            $example[$name] = $ex;
        }
        return $example;
    }

    /**
     * Get description by class name
     * @param string $className
     * @param array  $with
     * @return array|null
     */
    protected function getDescriptionByPHPDocTypeClass($className, $with = [])
    {
        $parser = new ClassParser($className);
        $properties = $parser->properties(true, false);
        if (!empty($with)) {
            $propertiesRead = $parser->propertiesRead($with, null, false);
            $properties = DumperYaml::merge($properties, $propertiesRead);
        }
        $propertiesByAnn = $this->getDescriptionByPropertyAnnotations($className);
        $properties = DumperYaml::merge($properties, $propertiesByAnn);
        if (empty($properties)) {
            return null;
        }
        $described = [];
        foreach ($properties as $name => $row) {
            $row = DumperYaml::merge(['name' => $name], $row);
            $nested = static::fromDescription($row);
            $described[$name] = $nested->describe();
        }
        return $described;
    }

    /**
     * Get properties by annotations
     * @param string $className
     * @return array
     */
    protected function getDescriptionByPropertyAnnotations($className)
    {
        /** @var \OA\Property[] $annotations */
        $annotations = $this->classAnnotations($className, 'OA\Property');
        $result = [];
        foreach ($annotations as $annotation) {
            $rowData = $annotation->toArray();
            $result[$annotation->name] = $rowData;
        }
        return $result;
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
     * Get fillable properties
     * @return array
     */
    public function getFillable()
    {
        return $this->fillable;
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

    /**
     * Create object from array description
     * @param array $config
     * @return Variable
     */
    public static function fromDescription($config)
    {
        return new static($config);
    }

    /**
     * Create object from example
     * @param mixed       $example
     * @param string|null $name
     * @param string|null $description
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
