<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parsers\ClassParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;

class ModelParser extends ClassParser
{
    public $className;

    protected $instance;

    protected $docFactory;

    /**
     * Get model properties
     * @param bool $onlyVisible
     * @return array
     */
    public function properties($onlyVisible = true)
    {
        $hidden = null;
        if ($onlyVisible) {
            $instance = $this->instantiate();
            if ($instance && method_exists($instance, 'getHidden')) {
                $hidden = $instance->getHidden();
            }
        }
        return $this->getPropertiesDescribed('property', null, $hidden);
    }

    /**
     * @param array|null $only
     * @param array|null $not
     * @return array|\phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public function propertiesRead($only = null, $not = null)
    {
        return $this->getPropertiesDescribed('property-read', $only, $not, true);
    }

    /**
     * @param string     $tag
     * @param array|null $only
     * @param array|null $not
     * @param bool       $describeClasses
     * @return array|\phpDocumentor\Reflection\DocBlock\Tag[]
     */
    protected function getPropertiesDescribed($tag = 'property', $only = null, $not = null, $describeClasses = false)
    {
        $properties = $this->getProperties($tag, $only, $not);
        $result = [];
        foreach ($properties as $property) {
            $type = (string)$property->getType();
            $name = $property->getVariableName();
            $description = $property->getDescription();
            if (is_string($type) && strpos($type, '|') !== false) {
                $type = explode('|', $type)[0];
            }
            $type = DumperYaml::normalizeType($type);
            if ($describeClasses && DumperYaml::isTypeClassName($type)) {
                $parser = new static($type);
                $result[$name] = DumperYaml::describe([]);
                Arr::set($result[$name], 'properties', $parser->properties());
            } else {
                $result[$name] = [
                    'type' => $type,
                    'description' => $description !== null ? $description->render() : '',
                ];
            }
        }
        return $result;
    }

    protected function isTypeClassName($type)
    {

    }

    /**
     * @param string     $tag
     * @param array|null $only
     * @param array|null       $not
     * @return array|Property|PropertyRead[]
     */
    protected function getProperties($tag = 'property', $only = null, $not = null)
    {
        $docStr = $this->docBlockClass($this->className);
        if ($docStr === null) {
            return [];
        }
        $doc = $this->getDocFactory()->create($docStr);
        /** @var Property[] $propertiesRaw */
        $propertiesRaw = $doc->getTagsByName($tag);
        $properties = [];
        foreach ($propertiesRaw as $property) {
            $properties[$property->getVariableName()] = $property;
        }
        if ($only !== null) {
            $only = array_combine($only, $only);
            $properties = array_intersect_key($properties, $only);
        }
        if ($not !== null) {
            $not = array_combine($not, $not);
            $properties = array_diff_key($properties, $not);
        }
        return $properties;
    }
}
