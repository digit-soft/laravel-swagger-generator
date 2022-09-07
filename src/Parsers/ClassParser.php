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
    public string $className;
    /**
     * @var array
     */
    public array $constructorParams = [];
    /**
     * Model class names list
     *
     * @var array
     */
    public array $modelClasses = [
        'Illuminate\Database\Eloquent\Model',
        'DigitSoft\StaticModels\Model',
    ];

    protected object $instance;

    protected bool $_isModel;

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
    public function properties(bool $onlyVisible = true, bool $describeClasses = true): array
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
    protected function getModelAppends($model): array
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
     * @return \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public function propertiesRead(?array $only = null, ?array $not = null, bool $describeClasses = true): array
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
     * @return \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    protected function getPropertiesDescribed(string $tag = 'property', ?array $only = null, ?array $not = null, bool $describeClasses = false): array
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
     * Check that class is a subclass of model.
     *
     * @return bool
     */
    protected function isModel(): bool
    {
        if (! isset($this->_isModel)) {
            $this->_isModel = ! empty(array_intersect($this->modelClasses, class_parents($this->className)));
        }

        return $this->_isModel;
    }

    /**
     * Get instance of the model.
     *
     * @return object
     */
    protected function instantiate(): object
    {
        return $this->instance ?? $this->instance = app()->make($this->className, $this->constructorParams);
    }
}
