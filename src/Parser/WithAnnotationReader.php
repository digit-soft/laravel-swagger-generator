<?php

namespace DigitSoft\Swagger\Parser;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Illuminate\Routing\Route;

/**
 * Trait WithAnnotationReader
 * @package DigitSoft\Swagger\Parser
 * @mixin WithRouteReflections
 */
trait WithAnnotationReader
{
    protected $annotationReader;

    protected $_classAnnotations;

    protected $_methodAnnotations;

    /**
     * Get route controller method annotation
     * @param Route  $route
     * @param string $name
     * @return object|null
     */
    protected function routeAnnotation(Route $route, $name = 'OA\Tag')
    {
        $ref = $this->routeReflection($route);
        if ($ref !== null && $ref instanceof \ReflectionMethod) {
            return $this->annotationReader()->getMethodAnnotation($ref, $name);
        }
        return null;
    }

    /**
     * Get route controller method annotations
     * @param Route       $route
     * @param string|null $name
     * @return array
     */
    protected function routeAnnotations(Route $route, $name = null)
    {
        $ref = $this->routeReflection($route);
        if (!$ref instanceof \ReflectionMethod) {
            return [];
        }
        $annotations = $this->methodAnnotations($ref);
        if ($name === null) {
            return $annotations;
        }
        $name = ltrim($name);
        $result = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }
        return $result;
    }

    /**
     * Get route controller method annotation
     * @param Route  $route
     * @param string $name
     * @return array
     */
    protected function controllerAnnotations(Route $route, $name = 'OA\Tag')
    {
        $ref = $this->routeReflection($route);
        if (!$ref instanceof \ReflectionMethod) {
            return [];
        }
        $annotations = $this->classAnnotations($ref->class);
        if ($name === null) {
            return $annotations;
        }
        $name = ltrim($name);
        $result = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }
        return $result;
    }

    /**
     * Get class annotations
     * @param string|object $class
     * @param string|null   $name
     * @return array
     */
    protected function classAnnotations($class, $name = null)
    {
        $className = is_string($class) ? $class : get_class($class);
        if (!isset($this->_classAnnotations[$className])) {
            $ref = $this->reflectionClass($className);
            $this->_classAnnotations[$className] = $this->annotationReader()->getClassAnnotations($ref);
        }
        if ($name === null) {
            return $this->_classAnnotations[$className];
        }
        $result = [];
        foreach ($this->_classAnnotations[$className] as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }
        return $result;
    }

    /**
     * @param \ReflectionMethod|array $ref
     * @param string|null             $name
     * @return array
     */
    protected function methodAnnotations($ref, $name = null)
    {
        if (is_array($ref)) {
            $ref = $this->reflectionMethod(...$ref);
        }
        $methodName = $ref->getName();
        if (!isset($this->_methodAnnotations[$methodName])) {
            $this->_methodAnnotations[$methodName] = $this->annotationReader()->getMethodAnnotations($ref);
        }
        if ($name === null) {
            return $this->_methodAnnotations[$methodName];
        }
        $result = [];
        foreach ($this->_methodAnnotations[$methodName] as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }
        return $result;
    }

    /**
     * Get annotaion reader
     * @return Reader
     */
    protected function annotationReader()
    {
        if ($this->annotationReader === null) {
            AnnotationRegistry::registerLoader('class_exists');
            $ignored = config('swagger-generator.ignoredAnnotationNames', []);
            foreach ($ignored as $item) {
                AnnotationReader::addGlobalIgnoredName($item);
            }
            $this->annotationReader = new AnnotationReader();
        }
        return $this->annotationReader;
    }
}
