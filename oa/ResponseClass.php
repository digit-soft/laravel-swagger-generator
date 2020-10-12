<?php

namespace OA;

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
    public $with;

    use WithReflections, WithAnnotationReader;

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        /** @var Symlink $symlink */
        $symlink = $this->classAnnotation($this->content, 'OA\Symlink');
        if ($symlink && $symlink->class !== $this->content) {
            return $this->withClass($symlink->class)->toArray();
        }

        return parent::toArray();
    }

    /**
     * Get clone with another class
     * @param  string $className
     * @return ResponseClass
     */
    protected function withClass($className)
    {
        $object = clone $this;
        $object->content = $className;
        return $object;
    }

    /**
     * @inheritdoc
     */
    protected function getContent()
    {
        if ($this->content === null || (! class_exists($this->content) && ! interface_exists($this->content))) {
            throw new \RuntimeException("Class or interface '{$this->content}' not found");
        }
        $properties = $this->getModelProperties();
        $this->_hasNoData = empty($properties) || empty($properties['properties']) ? true : $this->_hasNoData;

        return $properties;
    }

    /**
     * @inheritdoc
     */
    public function getComponentKey()
    {
        $className = explode('\\', $this->content);
        $key = end($className);
        if (!empty($this->with)) {
            $with = implode('_', $this->getWith());
            $key .= '__with_' . $with;
        }
        $key .= $this->asList || $this->asPagedList ? '__list' : '';
        $key .= $this->asPagedList ? '_paged' : '';
        return $key;
    }

    /**
     * Get model properties
     * @return array
     */
    protected function getModelProperties()
    {
        $variable = Variable::fromDescription([
            'type' => $this->content,
            'with' => $this->getWith(),
            'description' => $this->description,
        ]);

        return $variable->describe();
    }

    /**
     * Get property-read names
     * @return array
     */
    protected function getWith()
    {
        if (is_string($this->with)) {
            return $this->with = [$this->with];
        }
        return $this->with;
    }
}
