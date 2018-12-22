<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\DescribesVariables;
use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Support\Arr;

/**
 * @package OA
 */
abstract class BaseValueDescribed extends BaseAnnotation
{
    use DescribesVariables;

    /** @var string */
    public $name;
    /** @var string */
    public $type;
    /** @var string */
    public $format;
    /** @var string */
    public $description;
    /** @var mixed Example of variable */
    public $example;
    /** @var mixed Array item type */
    public $items;
    /** @var bool Flag that value is required */
    public $required;
    /** @var string */
    protected $phpType;

    protected $exampleRequired = false;

    protected $excludeKeys = [];

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
        if ($this->exampleRequired
            && !isset($data['example'])
            && ($example = DumperYaml::getExampleValue($this->type, $this->name)) !== null
        ) {
            $data['example'] = Arr::get($data, 'format') !== Variable::SW_FORMAT_BINARY ? $example : 'binary';
        }

        // Exclude undesirable keys
        if (!empty($this->excludeKeys)) {
            $data = Arr::except($data, $this->excludeKeys);
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
        $this->phpType = $this->type;
        // int[], string[] etc.
        if (($isArray = DumperYaml::isTypeArray($this->type)) === true) {
            $this->phpType = $this->type;
            $this->items = $this->items ?? DumperYaml::normalizeType($this->type, true);
        }
        // Convert PHP type to Swagger and vise versa
        if ($this->isPhpType($this->type)) {
            $this->type = static::swaggerType($this->type);
        } else {
            $this->phpType = static::phpType($this->type);
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
}
