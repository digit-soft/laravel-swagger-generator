<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parsers\ClassParser;
use Doctrine\Common\Annotations\Annotation\Target;

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
        return [
            'type' => 'object',
            'properties' => $this->getModelProperties(),
        ];
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
        $parser = new ClassParser($this->content);
        $properties = $parser->properties(true);
        $propertiesRead = [];
        if (($with = $this->getWith()) !== null) {
            $propertiesRead = $parser->propertiesRead($with);
        }
        $properties = array_merge($properties, $propertiesRead);
        $result = [];
        foreach ($properties as $varName => $varData) {
            $result[$varName] = $this->describeProperty($varName, $varData);
        }
        $propertiesAnn = $this->getModelPropertyAnnotations();
        $result = DumperYaml::merge($result, $propertiesAnn);
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
            $result[$annotation->name] = $annotation->toArray();
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

    /**
     * Describe model property
     * @param string $propName
     * @param array $propData
     * @return array
     */
    private function describeProperty($propName, $propData = [])
    {
        if (isset($propData['properties'])) {
            foreach ($propData['properties'] as $propNameNested => $propDataNested) {
                $propData['properties'][$propNameNested] = $this->describeProperty($propNameNested, $propDataNested);
            }
        } else {
            if (strpos($propData['type'], '|') !== false) {
                $propData['type'] = explode('|', $propData['type'])[0];
            }
            $propValue = DumperYaml::getExampleValue($propData['type'], $propName);
            $propData = array_merge($propData, DumperYaml::describe($propValue));
        }
        return $propData;
    }
}
