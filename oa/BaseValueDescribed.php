<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\DescribesVariables;
use DigitSoft\Swagger\Parser\WithFaker;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation\Enum;
use Illuminate\Support\Arr;

/**
 * @package OA
 */
abstract class BaseValueDescribed extends BaseAnnotation
{
    use WithFaker, DescribesVariables;

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
        if (!$this->isNested()) {
            $target[$this->name] = $this->toArray();
            return;
        }
        $nameArr = explode('.', $this->name);
        $currentTarget = &$target;
        while ($key = array_shift($nameArr)) {
            if (!empty($nameArr)) {
                if (!isset($currentTarget[$key])) {
                    $currentTarget[$key] = ['type' => 'object', 'properties' => []];
                }
                $currentTarget = &$currentTarget[$key]['properties'];
            } else {
                Arr::set($currentTarget, $key, $this->toArray());
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
        $optional = ['format', 'name', 'required', 'example', 'description'];
        foreach ($optional as $optKey) {
            $optValue = $this->{$optKey};
            if ($optValue !== null) {
                $data[$optKey] = $optValue;
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
        }
        // Write example if needed
        if ($this->isExampleRequired()
            && !isset($data['example'])
            && ($example = static::example($this->type, $this->name)) !== null
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

        return $data;
    }

    /**
     * Guess object properties key
     * @return array|null
     */
    protected function guessProperties()
    {
        if ($this->example !== null) {
            $described = Variable::fromExample($this->example, $this->name, $this->description)->describe();
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
        if ($this->example !== null) {
            return static::swaggerTypeByExample($this->example);
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
        if (($isArray = DumperYaml::isTypeArray($this->type)) === true) {
            $this->_phpType = $this->type;
            $this->items = $this->items ?? DumperYaml::normalizeType($this->type, true);
        }
        // Convert PHP type to Swagger and vise versa
        if ($this->isPhpType($this->type)) {
            $this->type = static::swaggerType($this->type);
        } else {
            $this->_phpType = static::phpType($this->type);
        }
    }

    /**
     * Check that given type is PHP type
     * @param  string $type
     * @return bool
     */
    protected function isPhpType($type)
    {
        if (DumperYaml::isTypeArray($type)) {
            return true;
        }
        $swType = static::swaggerType($type);
        return $swType !== $type;
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
