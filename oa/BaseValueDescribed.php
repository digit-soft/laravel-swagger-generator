<?php

namespace OA;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation\Enum;
use DigitSoft\Swagger\Parser\WithVariableDescriber;

/**
 * @property-read mixed|null $exampleProcessed Processed example
 * @package OA
 */
abstract class BaseValueDescribed extends BaseAnnotation
{
    use WithVariableDescriber;

    /**
     * @var string|null Variable name
     */
    public ?string $name = null;
    /**
     * @Enum({"string", "integer", "number", "boolean", "array", "object"})
     * @var string|null Swagger or PHP type
     */
    public ?string $type = null;
    /**
     * @Enum({"int32", "int64", "float", "byte", "date", "date-time", "password", "binary"})
     * @var string|null Format for swagger
     */
    public ?string $format = null;
    /**
     * @var bool Value may be null
     */
    public ?bool $nullable = null;
    /**
     * @var float|int|null Minimum value of number value
     */
    public int|float|null $minimum = null;
    /**
     * @var float|int|null Maximum value of number value
     */
    public int|float|null $maximum = null;
    /**
     * @var int|null Min length of string value
     */
    public ?int $minLength = null;
    /**
     * @var int|null Min length of string value
     */
    public ?int $maxLength = null;
    /**
     * @var array|null Array of possible values
     */
    public ?array $enum = null;
    /**
     * @var string|null Text description
     */
    public ?string $description = null;
    /**
     * @var mixed Example of variable (It's possible to set `example` to NULL)
     */
    public mixed $example = null;
    /**
     * @Enum({"string", "integer", "number", "boolean", "array", "object"})
     * @var mixed Array item type
     */
    public array|string|null $items = null;
    /**
     * @var bool Flag that value is required
     */
    public ?bool $required = null;
    /**
     * @var string|null
     */
    protected ?string $_phpType = null;

    /**
     * Check that variable name is nested (with dots)
     * @return bool
     */
    public function isNested(): bool
    {
        return $this->name !== null && str_contains($this->name, '.');
    }

    /**
     * Get name of the parent for nested.
     *
     * @return string|null
     */
    public function getNestedParentName(): ?string
    {
        [ , , , $parentName] = $this->getNestedPaths();

        return $parentName;
    }

    /**
     * Get paths for nested names.
     *
     * @return string[]
     */
    public function getNestedPaths(): array
    {
        $nameParts = explode('.', $this->name);
        if (count($nameParts) === 1) {
            return [null, null, $this->name, null];
        }
        $namePartsParent = $nameParts;
        array_pop($namePartsParent);
        $path = $this->makePathFromKeysArray($nameParts);
        $pathParent = $this->makePathFromKeysArray($namePartsParent);
        $lastName = last($nameParts);

        return [
            $path,                                  // Full path
            $pathParent,                            // Path to the parent
            $lastName !== '*' ? $lastName : null,   // Deepest variable name
            implode('.', $namePartsParent),         // Parent name
        ];
    }

    /**
     * Sets this array content to target by obtained key
     *
     * @param  array $target
     */
    public function toArrayRecursive(array &$target): void
    {
        $nameArr = explode('.', $this->name);
        $currentTarget = &$target;
        while ($key = array_shift($nameArr)) {
            $isArray = $key === '*';
            $hasNested = ! empty($nameArr);
            if ($isArray) {
                if (! isset($currentTarget['items'])) {
                    $currentTarget['type'] = 'array';
                    $currentTarget['items'] = [];
                }
                $currentTarget = &$currentTarget['items'];
            } else {
                if (! isset($currentTarget['properties'])) {
                    $currentTarget = ['type' => 'object', 'properties' => []];
                }
                $currentTarget['properties'][$key] = $currentTarget['properties'][$key] ?? [];
                $currentTarget = &$currentTarget['properties'][$key];
            }

            if (! $hasNested) {
                $currentTarget = empty($currentTarget) ? $this->toArray() : $this->describer()->merge($currentTarget, $this->toArray());
            }
        }
    }

    /**
     * BaseValueDescribed constructor.
     *
     * @param  array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'name');
        $this->processType();
    }

    /**
     * Get object string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        $exampleRequired = $this->isExampleRequired();
        $swType = $this->type ?? $this->guessType();
        $data = [];
        $attributesMap = ['example' => 'exampleProcessed'];
        $attributes = $this->getDumpedKeys();
        $excludeKeys = $this->getExcludedKeys();
        $excludeEmptyKeys = $this->getExcludedEmptyKeys();
        if (in_array('type', $attributes, true)) {
            $attributes = array_diff($attributes, ['type']);
            $data['type'] = $swType;
        }
        foreach ($attributes as $key) {
            $sourceKey = $attributesMap[$key] ?? $key;
            if (($attrValue = $this->{$sourceKey}) !== null) {
                $data[$key] = $attrValue;
            }
        }
        // Add properties to object
        if ($swType === Variable::SW_TYPE_OBJECT) {
            $data['properties'] = $this->guessProperties();
            // Remove `example` data if we have successfully got the properties
            if (! empty($data['properties'])) {
                unset($data['example']);
                $exampleRequired = false;
            }
        // Add items key to array
        } elseif ($swType === Variable::SW_TYPE_ARRAY) {
            $this->items = $this->items ?? 'string';
            $data['items'] = ['type' => $this->items];
            if (isset($data['format'])) {
                $data['items']['format'] = $data['format'];
                Arr::forget($data, ['format']);
            }
        }
        // Write example if needed
        if ($exampleRequired && ! isset($data['example'])) {
            $example = $this->describer()->example(null, $this->type, $this->name);
            // Get example one more time for PHP type (except PHP_ARRAY, SW_TYPE_OBJECT)
            $example = $example === null && $this->type !== Variable::SW_TYPE_OBJECT && ($phpType = $this->describer()->phpType($this->type)) !== $this->type
                ? $this->describer()->example($phpType, null, $this->name)
                : $example;
            if ($example !== null && $this->type !== null && $this->describer()->isValueSuitableForType($this->type, $example)) {
                $data['example'] = Arr::get($data, 'format') !== Variable::SW_FORMAT_BINARY ? $example : 'binary';
            }
        }
        // Exclude undesirable keys
        if (! empty($excludeKeys)) {
            $data = Arr::except($data, $excludeKeys);
        }
        // Exclude undesirable keys those are empty
        if (! empty($excludeEmptyKeys)) {
            $data = array_filter($data, function ($value, $key) use ($excludeEmptyKeys) {
                return ! in_array($key, $excludeEmptyKeys, true) || ! empty($value);
            }, ARRAY_FILTER_USE_BOTH);
        }
        // Remap schema children keys
        if ($this->isSchemaTypeUsed()) {
            $schemaKeys = ['type', 'format', 'items', 'enum', 'example'];
            foreach ($schemaKeys as $schemaKey) {
                if (($dataValue = $data[$schemaKey] ?? null) === null) {
                    continue;
                }
                $dataValue = $dataValue === static::NULL_VALUE ? null : $dataValue;
                Arr::set($data, 'schema.' . $schemaKey, $dataValue);
                Arr::forget($data, $schemaKey);
            }
            // Rewrite `example` key "NULL" => null
        } elseif (isset($data['example'])) {
            $data['example'] = $data['example'] === static::NULL_VALUE ? null : $data['example'];
        }

        return $data;
    }

    /**
     * Check that object has enum set
     *
     * @return bool
     */
    protected function hasEnum(): bool
    {
        return is_array($this->enum) && ! empty($this->enum);
    }

    /**
     * Make a path from keys array.
     *
     * @param  array $keys
     * @return string
     */
    protected function makePathFromKeysArray(array $keys): string
    {
        $first = array_shift($keys);
        $keys = array_map(fn ($k) => $k === '*' ? '.items' : '.properties.' . $k, $keys);
        array_unshift($keys, $first);

        return implode('', $keys);
    }

    /**
     * Get example with check by enum
     *
     * @return mixed
     */
    protected function getExampleProcessed(): mixed
    {
        $example = $this->example;
        if ($this->hasEnum()) {
            $example = $this->example !== null && in_array($this->example, $this->enum, false) ? $this->example : reset($this->enum);
        }

        return $example;
    }

    /**
     * Guess object properties key
     *
     * @return array
     */
    protected function guessProperties(): array
    {
        $example = $this->getExampleProcessed();
        $described = [];
        // By given example
        if ($example !== null) {
            $described = Variable::fromExample($example, $this->name, $this->description)->describe();
        // By PHP type
        } elseif ($this->_phpType !== null && $this->describer()->isTypeClassName($this->_phpType)) {
            $described = Variable::fromDescription(['type' => $this->_phpType])->describe(false);
        }

        return ! empty($described['properties']) ? $described['properties'] : [];
    }

    /**
     * Guess var type by example.
     *
     * @return string|null
     */
    protected function guessType(): ?string
    {
        $example = $this->getExampleProcessed();
        if ($example !== null) {
            return $this->describer()->swaggerTypeByExample($example);
        }

        return $this->type;
    }

    /**
     * Process type in object.
     */
    protected function processType(): void
    {
        if ($this->type === null) {
            return;
        }
        $this->_phpType = $this->type;
        // int[], string[] etc.
        if (($isArray = $this->describer()->isTypeArray($this->type)) === true) {
            $this->_phpType = $this->type;
            $this->items = $this->items ?? $this->describer()->normalizeType($this->type, true);
        }
        // Convert PHP type to Swagger and vise versa
        if ($this->isPhpType($this->type)) {
            $this->type = $this->describer()->swaggerType($this->type);
        } elseif ($this->describer()->isTypeClassName($this->type)) {
            $this->_phpType = $this->type;
            $this->type = Variable::SW_TYPE_OBJECT;
        } else {
            $this->_phpType = $this->describer()->phpType($this->type);
        }
    }

    /**
     * Check that given type is PHP type.
     *
     * @param  string $type
     * @return bool
     */
    protected function isPhpType(string $type): bool
    {
        if ($this->describer()->isTypeArray($type)) {
            return true;
        }
        $swType = $this->describer()->swaggerType($type);

        return $swType !== $type;
    }

    /**
     * Definition must include `schema` key and type, items, enum... keys must be present under that key.
     *
     * @return bool
     */
    protected function isSchemaTypeUsed(): bool
    {
        return false;
    }

    /**
     * Example required for this annotation.
     *
     * @return bool
     */
    protected function isExampleRequired(): bool
    {
        return false;
    }

    /**
     * Get keys that can be dumped to array.
     *
     * @return array
     */
    protected function getDumpedKeys(): array
    {
        return [
            'name', 'type', 'format', 'description',
            'example',
            'required', 'nullable', 'enum',
            'minimum', 'maximum', 'minLength', 'maxLength',
        ];
    }

    /**
     * Get keys that must be excluded.
     *
     * @return array
     */
    protected function getExcludedKeys(): array
    {
        return [];
    }

    /**
     * Get keys that must be excluded if they are empty.
     *
     * @return array
     */
    protected function getExcludedEmptyKeys(): array
    {
        return [];
    }
}
