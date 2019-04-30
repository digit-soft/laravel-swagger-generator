<?php

namespace OA;

use DigitSoft\Swagger\Parser\WithVariableDescriber;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation\Enum;
use Illuminate\Support\Arr;

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
     * @Enum({"string", "integer", "numeric", "boolean", "array", "object"})
     * @var string Swagger or PHP type
     */
    public $type;
    /**
     * @Enum({"int32", "int64", "float", "byte", "date", "date-time", "password", "binary"})
     * @var string Format for swagger
     */
    public $format;
    /**
     * @var array Array of possible values
     */
    public $enum;
    /**
     * @var string Text description
     */
    public $description;
    /**
     * @var mixed Example of variable
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
     * Sets this array content to target by obtained key
     * @param array $target
     */
    public function toArrayRecursive(&$target)
    {
        $nameArr = explode('.', $this->name);
        $currentTarget = &$target;
        while ($key = array_shift($nameArr)) {
            $isArray = $key === '*';
            $hasNested = !empty($nameArr);
            if ($isArray) {
                if (!isset($currentTarget['items'])) {
                    $currentTarget['type'] = 'array';
                    $currentTarget['items'] = [];
                }
                $currentTarget = &$currentTarget['items'];
            } else {
                if (!isset($currentTarget['properties'])) {
                    $currentTarget = ['type' => 'object', 'properties' => []];
                }
                $currentTarget['properties'][$key] = isset($currentTarget['properties'][$key]) ? $currentTarget['properties'][$key] : [];
                $currentTarget = &$currentTarget['properties'][$key];
            }

            if (!$hasNested) {
                $currentTarget = empty($currentTarget) ? $this->toArray() : $this->describer()->merge($currentTarget, $this->toArray());
            }
        }
    }

    /**
     * BaseValueDescribed constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'name');
        $this->processType();
    }

    /**
     * Get object string representation
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
        $optional = ['format', 'name', 'required', 'example' => 'exampleProcessed', 'description', 'enum'];
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
        if ($this->isExampleRequired()
            && !isset($data['example'])
            && ($example = $this->describer()->example($this->type, $this->name)) !== null
        ) {
            $data['example'] = Arr::get($data, 'format') !== Variable::SW_FORMAT_BINARY ? $example : 'binary';
        }

        $excludeKeys = $this->getExcludedKeys();
        $excludeEmptyKeys = $this->getExcludedEmptyKeys();

        // Exclude undesirable keys
        if (!empty($excludeKeys)) {
            $data = Arr::except($data, $excludeKeys);
        }

        // Exclude undesirable keys those are empty
        if (!empty($excludeEmptyKeys)) {
            $data = array_filter($data, function ($value, $key) use ($excludeEmptyKeys) {
                return !in_array($key, $excludeEmptyKeys) || !empty($value);
            }, ARRAY_FILTER_USE_BOTH);
        }
        // Remap schema children keys
        if ($this->isSchemaTypeUsed()) {
            $schemaKeys = ['type', 'items', 'example', 'format'];
            foreach ($schemaKeys as $schemaKey) {
                if (!isset($data[$schemaKey])) {
                    continue;
                }
                Arr::set($data, 'schema.' . $schemaKey, $data[$schemaKey]);
                Arr::forget($data, $schemaKey);
            }
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
     * Get example with check by enum
     * @return mixed
     */
    protected function getExampleProcessed()
    {
        $example = $this->example;
        if ($this->hasEnum()) {
            $example = in_array($this->example, $this->enum) ? $this->example : reset($this->enum);
        }
        return $example;
    }

    /**
     * Guess object properties key
     * @return array|null
     */
    protected function guessProperties()
    {
        $example = $this->getExampleProcessed();
        if ($example !== null) {
            $described = Variable::fromExample($example, $this->name, $this->description)->describe();
            return !empty($described['properties']) ? $described['properties'] : [];
        } elseif ($this->_phpType !== null && $this->describer()->isTypeClassName($this->_phpType)) {
            $described = Variable::fromDescription(['type' => $this->_phpType])->describe();
            return !empty($described['properties']) ? $described['properties'] : [];
        }
        return [];
    }

    /**
     * Guess var type by example
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
     * Process type in object
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
        } elseif($this->describer()->isTypeClassName($this->type)) {
            $this->_phpType = $this->type;
            $this->type = Variable::SW_TYPE_OBJECT;
        } else {
            $this->_phpType = $this->describer()->phpType($this->type);
        }
    }

    /**
     * Check that given type is PHP type
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
     * @return bool
     */
    protected function isSchemaTypeUsed()
    {
        return false;
    }

    /**
     * Example required for this annotation
     * @return bool
     */
    protected function isExampleRequired()
    {
        return false;
    }

    /**
     * Get keys that must be excluded
     * @return array
     */
    protected function getExcludedKeys()
    {
        return [];
    }

    /**
     * Get keys that must be excluded if they are empty
     * @return array
     */
    protected function getExcludedEmptyKeys()
    {
        return [];
    }
}
