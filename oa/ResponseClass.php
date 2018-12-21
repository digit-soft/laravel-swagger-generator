<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Support\Arr;

/**
 * Used to describe controller action response.
 * Data will be gathered from given class PHPDoc block.
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class ResponseClass extends Response
{
    public $with;

    use WithReflections, WithAnnotationReader;

    /**
     * @inheritdoc
     */
    protected function getContent()
    {
        if ($this->content === null || !class_exists($this->content)) {
            throw new \Exception("Class '{$this->content}' not found");
        }
        $properties = $this->getModelProperties();
        $this->hasNoData = empty($properties) || empty($properties['properties']) ? true : $this->hasNoData;
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
        $result = $variable->describe();
        $propertiesAnn = $this->getModelPropertyAnnotations();
        $result['properties'] = DumperYaml::merge($result['properties'], $propertiesAnn);
        return $result;
    }

    /**
     * Get properties by annotations
     * @return array
     */
    protected function getModelPropertyAnnotations()
    {
        /** @var \OA\Property[] $annotations */
        $annotations = $this->classAnnotations($this->content, 'OA\Property');
        $result = [];
        foreach ($annotations as $annotation) {
            $rowData = $annotation->toArray();
            Arr::forget($rowData, ['name']);
            $result[$annotation->name] = $rowData;
        }
        return $result;
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
