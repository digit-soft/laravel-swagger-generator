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
     * @var string Variable name
     */
    public $name;
    /**
     * @Enum({"string", "integer", "number", "boolean", "array", "object"})
     * @var string Swagger or PHP type
     */
    public $type;
    /**
     * @Enum({"int32", "int64", "float", "byte", "date", "date-time", "password", "binary"})
     * @var string Format for swagger
     */
    public $format;
    /**
     * @var bool Value may be null
     */
    public $nullable;
    /**
     * @var float|int Minimum value of number value
     */
    public $minimum;
    /**
     * @var float|int Maximum value of number value
     */
    public $maximum;
    /**
     * @var int Min length of string value
     */
    public $minLength;
    /**
     * @var int Min length of string value
     */
    public $maxLength;
    /**
     * @var array Array of possible values
     */
    public $enum;
    /**
     * @var string Text description
     */
    public $description;
    /**
     * @var mixed Example of variable (It's possible to set `example` to NULL)
     */
    public $example;
    /**
     * @Enum({"string", "integer", "numeric", "boolean", "array", "object"})
     * @var mixed Array item type
     */
    public $items;
    /**
     * @var bool Flag that value is required
     */
    public $required;
    /**
     * @var string
     */
    protected $_phpType;

    /**
     * Check that variable name is nested (with dots)
     * @return bool
     */
    public function isNested()
    {
        return $this->name !== null && strpos($this->name, '.') !== false;
    }

    /**
     * Get name of the parent for nested.
     *
     * @return string|null
     */
    public function getNestedParentName()
    {
        [ , , , $parentName] = $this->getNestedPaths();

        return $parentName;
    }

    /**
     * Get paths for nested names.
     *
     * @return string[]
     */
    public function getNestedPaths()
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
    public function toArrayRecursive(&$target)
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
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
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
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $swType = $this->type ?? $this->guessType();
        $data = [
            'type' => $swType,
        ];
        $optional = [
            'format', 'name', 'required', 'description', 'enum',
            'nullable', 'minimum', 'maximum', 'minLength', 'maxLength',
            'example' => 'exampleProcessed',
        ];
        foreach ($optional as $arrKey => $optKey) {
            $arrKey = is_numeric($arrKey) ? $optKey : $arrKey;
            $optValue = $this->{$optKey};
            if ($optValue !== null) {
                $data[$arrKey] = $optValue;
            }
        }
        // Add properties to object
        if ($swType === Variable::SW_TYPE_OBJECT) {
            $data['properties'] = $this->guessProperties();
        }
        // Add items key to array
        if ($swType === Variable::SW_TYPE_ARRAY) {
            $this->items = $this->items ?? 'string';
            $data['items'] = ['type' => $this->items];
            if (isset($data['format'])) {
                $data['items']['format'] = $data['format'];
                Arr::forget($data, ['format']);
            }
        }
        // Write example if needed
        if (! isset($data['example']) && $this->isExampleRequired()) {
            $example = $this->describer()->example(null, $this->type, $this->name);
            // Get example one more time for PHP type (except PHP_ARRAY, SW_TYPE_OBJECT)
            $example = $example === null && $this->type !== Variable::SW_TYPE_OBJECT && ($phpType = $this->describer()->phpType($this->type)) !== $this->type
                ? $this->describer()->example($phpType, null, $this->name)
                : $example;
            if ($example !== null && $this->describer()->isValueSuitableForType($this->type, $example)) {
                $data['example'] = Arr::get($data, 'format') !== Variable::SW_FORMAT_BINARY ? $example : 'binary';
            }
        }

        $excludeKeys = $this->getExcludedKeys();
        $excludeEmptyKeys = $this->getExcludedEmptyKeys();

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
            $schemaKeys = ['type', 'items', 'example', 'format'];
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
     * @return bool
     */
    protected function hasEnum()
    {
        return is_array($this->enum) && !empty($this->enum);
    }

    /**
     * Make a path from keys array.
     *
     * @param  array $keys
     * @return string
     */
    protected function makePathFromKeysArray(array $keys)
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
    protected function getExampleProcessed()
    {
        $example = $this->example;
        if ($this->hasEnum()) {
            $example = in_array($this->example, $this->enum, false) ? $this->example : reset($this->enum);
        }

        return $example;
    }

    /**
     * Guess object properties key
     *
     * @return array|null
     */
    protected function guessProperties()
    {
        $example = $this->getExampleProcessed();
        if ($example !== null) {
            $described = Variable::fromExample($example, $this->name, $this->description)->describe();

            return ! empty($described['properties']) ? $described['properties'] : [];
        }

        if ($this->_phpType !== null && $this->describer()->isTypeClassName($this->_phpType)) {
            $described = Variable::fromDescription(['type' => $this->_phpType])->describe();

            return ! empty($described['properties']) ? $described['properties'] : [];
        }

        return [];
    }

    /**
     * Guess var type by example.
     *
     * @return string|null
     */
    protected function guessType()
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
    protected function processType()
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
    protected function isPhpType($type)
    {
        if ($this->describer()->isTypeArray($type)) {
            return true;
        }
        $swType = $this->describer()->swaggerType($type);

        return $swType !== $type;
    }

    /**
     * Definition must include `schema` key and type, items... keys must be present under that key.
     *
     * @return bool
     */
    protected function isSchemaTypeUsed()
    {
        return false;
    }

    /**
     * Example required for this annotation.
     *
     * @return bool
     */
    protected function isExampleRequired()
    {
        return false;
    }

    /**
     * Get keys that must be excluded.
     *
     * @return array
     */
    protected function getExcludedKeys()
    {
        return [];
    }

    /**
     * Get keys that must be excluded if they are empty.
     *
     * @return array
     */
    protected function getExcludedEmptyKeys()
    {
        return [];
    }
}
