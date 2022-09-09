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

    public array $with = [];

    public array $except = [];

    /**
     * @inheritdoc
     */
    public function getComponentKey(): ?string
    {
        $className = explode('\\', $this->content);
        $key = end($className);
        if (! empty($this->with)) {
            $key .= '__w_' . str_replace(['\\', '.'], '_', implode('_', $this->with));
        }
        if (! empty($this->except)) {
            $key .= '__wo_' . str_replace(['\\', '.'], '_', implode('_', $this->except));
        }
        $key .= $this->asList || $this->asPagedList ? '__list' : '';
        $key .= $this->asPagedList ? '_paged' : '';
        $key .= $this->asCursorPagedList ? '_paged_cursor' : '';

        return $key;
    }

    /**
     * @inheritdoc
     */
    protected function getContent(): ?array
    {
        $properties = $this->getModelProperties($this->content);
        $this->_hasNoData = empty($properties) || empty($properties['properties']) ? true : $this->_hasNoData;

        return $properties;
    }

    /**
     * Get model properties
     *
     * @param  string $className
     * @param  array  $except
     * @return array
     */
    protected function getModelProperties(string $className, array $except = []): array
    {
        if (! class_exists($className) && ! interface_exists($className)) {
            throw new \RuntimeException("Class or interface '{$className}' not found");
        }

        $variable = Variable::fromDescription([
            'type' => $className,
            'with' => $this->with,
            'except' => array_unique(array_merge($this->except, $except)),
        ]);

        return $variable->describe();
    }

    /**
     * Get ignored properties.
     *
     * @param  string[] $classNames
     * @return string[]
     */
    protected function getModelsIgnoredProperties(array $classNames): array
    {
        $ignored = [];
        foreach ($classNames as $className) {
            /** @var \OA\PropertyIgnore[] $annotations */
            $annotations = $this->classAnnotations($className, \OA\PropertyIgnore::class);
            $ignored[] = Arr::pluck($annotations, 'name', 'name');
        }

        return ! empty($ignored) ? array_keys(array_merge([], ...$ignored)) : [];
    }
}
