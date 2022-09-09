<?php

namespace DigitSoft\Swagger\Yaml;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Parsers\ClassParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithVariableDescriber;
use DigitSoft\Swagger\Describer\CollectsClassReferences;

class Variable
{
    use WithReflections, WithAnnotationReader, WithVariableDescriber, CollectsClassReferences;

    const KEY_EXAMPLE = 'example';
    const KEY_TYPE = 'type';
    const KEY_DESC = 'description';
    const KEY_NAME = 'name';
    const KEY_WITH = 'with';
    const KEY_EXCEPT = 'except';
    const KEY_ONLY = 'only';
    const KEY_PROPERTIES = 'properties';

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

    public mixed $example = null;

    public ?string $description = null;

    public ?string $type = null;

    public string|array|null $items = null;

    public ?string $name = null;

    public ?array $properties = null;

    public array $with = [];

    public array $except = [];

    public array $only = [];

    protected array $descriptionsNested = [];

    protected array $fillable = [
        self::KEY_NAME,
        self::KEY_DESC,
        self::KEY_TYPE,
        self::KEY_WITH,
        self::KEY_EXCEPT,
        self::KEY_ONLY,
        self::KEY_PROPERTIES,
    ];

    protected ?string $swaggerType = null;

    protected static array $_cache_objects = [];

    /**
     * Variable constructor.
     * @param  array $config
     */
    public function __construct(array $config = [])
    {
        $this->configureSelf($config);
    }

    /**
     * Get object array representation
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        $this->fillMissingProperties();
        foreach ($this->getFillable() as $paramName) {
            $value = $this->{$paramName};
            if ($value === null && ! in_array($paramName, [static::KEY_TYPE, static::KEY_PROPERTIES], true)) {
                continue;
            }
            $result[$paramName] = $value;
        }

        return $result;
    }

    /**
     * Describe variable
     *
     * @param  bool $useRef
     * @return array
     */
    public function describe(bool $useRef = true): array
    {
        $this->fillMissingProperties();
        $typeSwagger = $this->getSwType();
        $result = [
            'type' => $typeSwagger,
        ];
        if (isset($this->description)) {
            $result['description'] = trim($this->description);
        }
        // Set example if it was provided and not empty for array and object type
        if (
            $this->example !== null
            && (
                ! in_array($typeSwagger, [static::SW_TYPE_OBJECT, static::SW_TYPE_ARRAY], true)
                || ! empty($this->example)
            )
        ) {
            $result['example'] = $this->example;
        }
        $res = [];
        switch ($typeSwagger) {
            case static::SW_TYPE_OBJECT:
                $className = $this->describer()->normalizeType($this->type);
                $classNameExists = $this->describer()->isTypeClassName($className);
                if ($useRef && $classNameExists && ($ref = static::getCollectedClassReference($className, $this->with, $this->except, $this->only)) !== null) {
                    // dump($ref, $className);
                    return $ref;
                }
                $res = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
                // If class does not exist then $objCacheKey will be NULL
                if ($classNameExists) {
                    $properties = [];
                    $propsIgnored = $this->getClassIgnoredProperties($className);
                    $symlinkClasses = [$className => ['merge' => true, 'ignore' => $propsIgnored]];
                    $symlinkClass = $className;
                    /** @var $symlink \OA\Symlink|null */
                    while (($symlink = $this->classAnnotation($symlinkClass, \OA\Symlink::class)) !== null && ! isset($symlinkClasses[$symlink->class])) {
                        $symlinkClasses[$symlinkClass]['merge'] = $symlink->merge;
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $propsIgnored = array_merge($propsIgnored, $this->getClassIgnoredProperties($symlink->class));
                        $symlinkClasses[$symlink->class] = ['merge' => true, 'ignore' => $propsIgnored];
                        $symlinkClass = $symlink->class;
                    }
                    $symlinkClasses = array_filter($symlinkClasses, fn ($r) => ! empty($r['merge']));
                    foreach ($symlinkClasses as $classNameToParse => $row) {
                        $propertiesCurrent = $this->getDescriptionByPHPDocTypeClass($classNameToParse, $this->with ?? []);
                        if (! empty($row['ignore'])) {
                            $propertiesCurrent = array_diff_key($propertiesCurrent, $row['ignore']);
                        }
                        $properties[] = $propertiesCurrent;
                    }
                    // Write properties from class and symlinks
                    $res['properties'] = ! empty($properties) ? $this->describer()->mergeWithPropertiesRewrite(...$properties) : [];
                    // Get description from a class PHPDoc directly
                    $res['description'] = $this->description ?? $this->getDescriptionSummaryByPHPDocTypeClass($className);
                } elseif (is_array($this->example) && Arr::isAssoc($this->example)) {
                    $describedEx = $this->describer()->describe($this->example); // W/o nested examples
                    $res['properties'] = Arr::get($describedEx, 'properties', []);
                    // Remove already described example
                    Arr::forget($result, 'example');
                }
                // Merge previously set properties
                if (is_array($this->properties) && ! empty($this->properties)) {
                    $res['properties'] = $this->describer()->merge($res['properties'], $this->properties);
                }
                $res['properties'] = ! empty($this->except) ? Arr::except($res['properties'], $this->except) : $res['properties'];
                // Write `$ref` for the class
                if ($useRef && $classNameExists) {
                    return static::setCollectedClassReference(
                        $className, $this->describer()->merge($res, $result), $this->with, $this->except, $this->only
                    );
                }
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

        return $this->describer()->merge($res, $result);
    }

    /**
     * Get ignored properties.
     *
     * @param  string $className
     * @return string[]
     */
    protected function getClassIgnoredProperties(string $className): array
    {
        $annotations = $this->classAnnotations($className, \OA\PropertyIgnore::class);

        return Arr::pluck($annotations, 'name', 'name');
    }

    /**
     * Get fillable properties
     * @return array
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Describe self as a PHP class.
     *
     * @return array
     * @throws \Throwable
     */
    protected function describeAsClass(): array
    {
        $className = $this->describer()->normalizeType($this->type);
        $result = ['type' => static::SW_TYPE_OBJECT, 'properties' => []];
        if (class_exists($className)) {
            $result['properties'] = $this->getDescriptionByPHPDocTypeClass($className, $this->with ?? []);
            $result['properties'] = ! empty($this->except) ? Arr::except($result['properties'], $this->except) : $result['properties'];
        }

        return $result;
    }

    /**
     * Get swagger type.
     *
     * @return string|null
     */
    protected function getSwType(): ?string
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
                $swType = $simplifiedType === $phpType ? static::SW_TYPE_OBJECT : $simplifiedType;
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
    protected function fillMissingProperties(): void
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
    protected function getPHPDocType(mixed $value): ?string
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
    protected function getExampleByPHPDocType(string $phpType): mixed
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
    protected function getExampleByPHPDocTypeClass(string $className): ?array
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
     * @return array
     */
    protected function getDescriptionByPHPDocTypeClass(string $className, array $with = []): array
    {
        $parser = new ClassParser($className);
        $properties = $parser->properties(true, false);
        $propertiesRead = [];
        $propertiesByAnnRead = [];
        if (! empty($with)) {
            $this->setWithToPropertiesRecursively($properties, $with);
            $propertiesRead = $parser->propertiesRead($with, null, false);
            $propertiesByAnnRead = $this->getDescriptionByPropertyAnnotations($className, $with, \OA\PropertyRead::class);
        }
        $propertiesByAnn = $this->getDescriptionByPropertyAnnotations($className);
        $properties = $this->describer()->merge($properties, $propertiesRead, $propertiesByAnn, $propertiesByAnnRead);
        if (empty($properties)) {
            return [];
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
     * Get description from a class PHPDoc.
     *
     * @param  string $className
     * @return string|null
     */
    protected function getDescriptionSummaryByPHPDocTypeClass(string $className): ?string
    {
        return (new ClassParser($className))->docSummary();
    }

    /**
     * Get properties by annotations
     *
     * @param  string $className
     * @param  array  $only
     * @param  string $annotationClass
     * @return array
     */
    protected function getDescriptionByPropertyAnnotations(string $className, array $only = [], string $annotationClass = \OA\Property::class): array
    {
        /** @var \OA\Property[] $annotations */
        $annotations = $this->classAnnotations($className, $annotationClass);
        $result = [];
        foreach ($annotations as $annotation) {
            $rowData = $annotation->toArray();
            if (! empty($only) && ! in_array($annotation->name, $only, true) && ! $annotation->isNested()) {
                continue;
            }
            // Skip annotations w/o name
            if (empty($annotation->name)) {
                continue;
            }
            // Handle nested names (with dots)
            if (
                $annotation->isNested()
                && ([$nestedPath, $nestedParentPath] = $annotation->getNestedPaths())
                && $nestedParentPath !== null
                && ($nestedParentType = Arr::get($result, $nestedParentPath . '.type')) !== null
                && in_array($nestedParentType, [static::SW_TYPE_ARRAY, static::SW_TYPE_OBJECT], true)
            ) {
                unset($rowData['name']);
                Arr::set($result, $nestedPath, $rowData);
                continue;
            }
            $result[$annotation->name] = $rowData;
        }

        // Cleanup nested annotations
        return ! empty($only) ? array_intersect_key($result, array_flip($only)) : $result;
    }

    /**
     * Get ignored properties.
     *
     * @param  string $className
     * @return array
     */
    protected function getIgnoredProperties(string $className): array
    {
        /** @var \OA\PropertyIgnore[] $annotations */
        $annotations = $this->classAnnotations($className, \OA\PropertyIgnore::class);

        return Arr::pluck($annotations, 'name', 'name');
    }

    /**
     * Set `with` key to describable properties recursively.
     *
     * @param  array $properties
     * @param  array $with
     */
    protected function setWithToPropertiesRecursively(array &$properties, array $with = []): void
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
     * Configure object itself.
     *
     * @param  array $config
     */
    protected function configureSelf(array $config = []): void
    {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Get magic method.
     *
     * @param  string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get(string $name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        throw new \RuntimeException("Property {$name} does not exist or is not readable");
    }

    /**
     * Set magic method.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return mixed
     * @throws \Exception
     */
    public function __set(string $name, mixed $value)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}($value);
        }
        throw new \RuntimeException("Property {$name} does not exist or is not writable");
    }

    /**
     * ISSET magic.
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->{$method}() !== null;
        }

        return false;
    }

    /**
     * Create object from array description
     *
     * @param  array $config
     * @return Variable
     */
    public static function fromDescription(array $config): static
    {
        return new static($config);
    }

    /**
     * Create object from example
     *
     * @param  mixed       $example
     * @param  string|null $name
     * @param  string|null $description
     * @return Variable
     */
    public static function fromExample(mixed $example, ?string $name = null, ?string $description = null): static
    {
        $config = [
            'example' => $example,
            'name' => $name,
            'description' => $description,
        ];

        return new static($config);
    }
}
