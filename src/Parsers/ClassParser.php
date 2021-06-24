<?php

namespace DigitSoft\Swagger\Parsers;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Parser\WithDocParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithVariableDescriber;

class ClassParser
{
    use WithReflections, WithDocParser, WithVariableDescriber;

    /**
     * @var string
     */
    public $className;
    /**
     * @var array
     */
    public $constructorParams = [];
    /**
     * Model class names list
     * @var array
     */
    public $modelClasses = [
        'Illuminate\Database\Eloquent\Model',
        'DigitSoft\StaticModels\Model',
    ];

    protected $instance;

    protected $_isModel;

    /**
     * ModelParser constructor.
     *
     * @param $className
     */
    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * Get model properties
     *
     * @param  bool $onlyVisible
     * @param  bool $describeClasses
     * @return array
     */
    public function properties(bool $onlyVisible = true, bool $describeClasses = true)
    {
        $appends = [];
        $hidden = null;
        if ($this->isModel()) {
            /** @var \Illuminate\Database\Eloquent\Model|\DigitSoft\StaticModels\Model $instance */
            $instance = $this->instantiate();
            $hidden = $onlyVisible ? $instance->getHidden() : null;
            $appends = $this->getModelAppends($instance);
        }
        $properties = $this->getPropertiesDescribed('property', null, $hidden, $describeClasses);

        return ! empty($appends)
            ? $this->describer()->merge($properties, $this->getPropertiesDescribed('property-read', $appends, null, $describeClasses))
            : $properties;
    }

    /**
     * Get model appends attribute.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    protected function getModelAppends($model)
    {
        $ref = $this->reflectionProperty($model, 'appends');
        $ref->setAccessible(true);

        return $ref->getValue($model);
    }

    /**
     * Get `property-read` tags.
     *
     * @param  array|null $only
     * @param  array|null $not
     * @param  bool       $describeClasses
     * @return array|\phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public function propertiesRead(?array $only = null, ?array $not = null, bool $describeClasses = true)
    {
        return $this->getPropertiesDescribed('property-read', $only, $not, $describeClasses);
    }

    /**
     * Get described properties.
     *
     * @param  string     $tag
     * @param  array|null $only
     * @param  array|null $not
     * @param  bool       $describeClasses
     * @return array|\phpDocumentor\Reflection\DocBlock\Tag[]
     */
    protected function getPropertiesDescribed(string $tag = 'property', ?array $only = null, ?array $not = null, bool $describeClasses = false)
    {
        $docStr = $this->docBlockClass($this->className);
        if ($docStr === null) {
            return [];
        }

        $properties = $this->getDocTagsPropertiesDescribed($docStr, $tag, $only, $not);
        if (! $describeClasses) {
            return $properties;
        }

        // Describe only first level class objects
        foreach ($properties as $key => $row) {
            $row['type'] = $this->describer()->normalizeType($row['type']);
            if ($this->describer()->isTypeClassName($row['type'])) {
                $properties[$key] = $this->describer()->describe([]);
                $classDescription = (new static($this->describer()->normalizeType($row['type'], true)))->properties();
                Arr::set($properties[$key], 'properties', $classDescription);
                if ($this->describer()->isTypeArray($row['type'])) {
                    $propertyArray = $this->describer()->describe(['']);
                    Arr::set($propertyArray, 'items', $properties[$key]);
                    $properties[$key] = $propertyArray;
                }
            }
        }

        return $properties;
    }

    /**
     * Check that class is model subclass
     * @return bool
     */
    protected function isModel()
    {
        if ($this->_isModel === null) {
            $this->_isModel = ! empty(array_intersect($this->modelClasses, class_parents($this->className)));
        }

        return $this->_isModel;
    }

    /**
     * @return object
     */
    protected function instantiate()
    {
        return $this->instance ?? $this->instance = app()->make($this->className, $this->constructorParams);
    }
}
