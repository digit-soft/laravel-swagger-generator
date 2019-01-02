<?php

namespace DigitSoft\Swagger\Parser;

use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;

/**
 * Add PHPDoc parse ability to class
 *
 * @package DigitSoft\Swagger\Parser
 * @mixin WithVariableDescriber
 */
trait WithDocParser
{
    protected $docFactory;

    /**
     * Get summary from PHPDoc block
     * @param string $docStr
     * @return string
     */
    protected function getDocSummary($docStr)
    {
        if (!is_string($docStr)) {
            return '';
        }
        $docblock = $this->getDocFactory()->create($docStr);
        return $docblock->getSummary();
    }

    /**
     * Get doc tags
     * @param string      $docStr
     * @param string|null $tagName
     * @return \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    protected function getDocTags($docStr, $tagName = null)
    {
        if (!is_string($docStr)) {
            return [];
        }
        $docBlock = $this->getDocFactory()->create($docStr);
        return $tagName !== null ? $docBlock->getTagsByName($tagName) : $docBlock->getTags();
    }

    /**
     * Get property or param tags as info array
     * @param string     $docStr PHPDoc string
     * @param string     $tagName Tag name (property, property-read, property-write, param)
     * @param array|null $only array with permitted variable names
     * @param array|null $not  array with NOT permitted variable names
     * @return array Info about tags indexed by variable name
     */
    protected function getDocTagsPropertiesDescribed($docStr, $tagName = 'property', $only = null, $not = null)
    {
        $tags = $this->getDocTagsProperties($docStr, $tagName, $only, $not);
        if (empty($tags)) {
            return [];
        }
        $result = [];
        foreach ($tags as $tag) {
            $type = (string)$tag->getType();
            $name = $tag->getVariableName();
            $description = $tag->getDescription();
            $type = $this->describer()->normalizeType($type);
            $result[$name] = [
                'type' => $type,
                'description' => $description !== null ? $description->render() : null,
            ];
        }
        return $result;
    }

    /**
     * Get property or param tags
     * @param string     $docStr PHPDoc string
     * @param string     $tagName Tag name (property, property-read, property-write, param)
     * @param array|null $only array with permitted variable names
     * @param array|null $not  array with NOT permitted variable names
     * @return Property[]|PropertyRead[]|PropertyWrite[]
     */
    protected function getDocTagsProperties($docStr, $tagName = 'property', $only = null, $not = null)
    {
        /** @var Property[]|PropertyRead[]|PropertyWrite[]|Param[] $propertiesRaw */
        $propertiesRaw = $this->getDocTags($docStr, $tagName);
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

    /**
     * Get Doc factory
     * @return \phpDocumentor\Reflection\DocBlockFactory
     */
    protected function getDocFactory()
    {
        if ($this->docFactory === null) {
            $this->docFactory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        }
        return $this->docFactory;
    }
}
