<?php

namespace OA;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to describe controller action response.
 * Data will be gathered from given class PHPDoc block.
 *
 * @Annotation
 * @Target("METHOD")
 * @Attributes({
 *   @Attribute("with",type="array"),
 * })
 */
class ResponseClass extends Response
{
    use WithReflections, WithAnnotationReader;

    public $with;

    public $except = [];

    /**
     * @var array Array of class names to merge with
     */
    protected $mergeWith = [];

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        /** @var Symlink $symlink */
        $symlink = $this->classAnnotation($this->content, 'OA\Symlink');
        if ($symlink && $symlink->class !== $this->content) {
            return $this->withClass($symlink->class, $symlink->merge)->toArray();
        }

        return parent::toArray();
    }

    /**
     * @inheritdoc
     */
    public function getComponentKey()
    {
        $className = explode('\\', $this->content);
        $key = end($className);
        if (! empty($this->with)) {
            $with = implode('_', $this->getWith());
            $key .= '__w_' . $with;
        }
        if (! empty($this->except)) {
            $with = implode('_', $this->getExcept());
            $key .= '__wo_' . $with;
        }
        $key .= $this->asList || $this->asPagedList ? '__list' : '';
        $key .= $this->asPagedList ? '_paged' : '';
        $key .= $this->asCursorPagedList ? '_paged_cursor' : '';

        return $key;
    }

    /**
     * @inheritdoc
     */
    protected function getContent()
    {
        $classNames = array_merge([$this->content], $this->mergeWith);
        $propertiesToMerge = [];
        $propertiesToIgnore = $this->getModelsIgnoredProperties($classNames);
        foreach ($classNames as $className) {
            $propertiesToMerge[] = $this->getModelProperties($className, $propertiesToIgnore);
        }
        $properties = ! empty($propertiesToMerge) ? $this->describer()->mergeWithPropertiesRewrite([], ...$propertiesToMerge) : [];
        $this->_hasNoData = empty($properties) || empty($properties['properties']) ? true : $this->_hasNoData;


        return $properties;
    }

    /**
     * Get clone with another class
     *
     * @param  string $className
     * @param  bool   $merge
     * @return ResponseClass
     */
    protected function withClass($className, $merge = false)
    {
        $object = clone $this;
        $object->content = $className;
        if ($merge) {
            $object->mergeWith[] = $this->content;
        }

        return $object;
    }

    /**
     * Add class name to merge with.
     *
     * @param  string $className
     * @return $this
     */
    protected function addClassToMerge($className)
    {
        $this->mergeWith[] = $className;

        return $this;
    }

    /**
     * Get model properties
     *
     * @param  string $className
     * @param  array  $except
     * @return array
     */
    protected function getModelProperties($className, $except = [])
    {
        if ($className === null || (! class_exists($className) && ! interface_exists($className))) {
            throw new \RuntimeException("Class or interface '{$className}' not found");
        }

        $variable = Variable::fromDescription([
            'type' => $className,
            'with' => $this->getWith(),
            'except' => array_unique(array_merge($this->getExcept(), $except)),
            'description' => $this->description,
        ]);

        return $variable->describe();
    }

    /**
     * Get ignored properties.
     *
     * @param  string[] $classNames
     * @return array
     */
    protected function getModelsIgnoredProperties($classNames)
    {
        $ignored = [];
        foreach ($classNames as $className) {
            /** @var \OA\PropertyIgnore[] $annotations */
            $annotations = $this->classAnnotations($className, 'OA\PropertyIgnore');
            $ignored[] = Arr::pluck($annotations, 'name', 'name');
        }

        return ! empty($ignored) ? array_keys(array_merge([], ...$ignored)) : [];
    }

    /**
     * Get property-read names
     *
     * @return array
     */
    protected function getWith()
    {
        if (is_string($this->with)) {
            return $this->with = [$this->with];
        }

        return $this->with;
    }

    /**
     * Get property names to avoid.
     *
     * @return array
     */
    protected function getExcept()
    {
        if (is_string($this->except)) {
            return $this->except = [$this->except];
        }

        return $this->except;
    }
}
