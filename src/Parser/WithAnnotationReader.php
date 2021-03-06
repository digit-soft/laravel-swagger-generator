<?php

namespace DigitSoft\Swagger\Parser;

use OA\BaseAnnotation;
use Illuminate\Support\Arr;
use Illuminate\Routing\Route;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * Trait WithAnnotationReader
 * @mixin WithRouteReflections
 */
trait WithAnnotationReader
{
    protected $annotationReader;
    /** @var \OA\BaseAnnotation[][] */
    protected $_classAnnotations;
    /** @var \OA\BaseAnnotation[][] */
    protected $_methodAnnotations;

    /**
     * Get route controller method annotation
     *
     * @param  Route  $route
     * @param  string $name
     * @return BaseAnnotation|null
     */
    protected function routeAnnotation(Route $route, $name = 'OA\Tag')
    {
        $annotations = $this->routeAnnotations($route, $name);

        return ! empty($annotations) ? reset($annotations) : null;
    }

    /**
     * Get route controller method annotations
     *
     * @param  Route       $route
     * @param  string|null $name
     * @return BaseAnnotation[]
     */
    protected function routeAnnotations(Route $route, $name = null)
    {
        $ref = $this->routeReflection($route);
        if (! $ref instanceof \ReflectionMethod) {
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
     * Get route controller annotations
     *
     * @param  Route       $route
     * @param  string|null $name
     * @param  bool        $checkExtending
     * @param  bool        $mergeExtended
     * @return BaseAnnotation[]
     */
    protected function controllerAnnotations(Route $route, $name = null, $checkExtending = false, $mergeExtended = false)
    {
        $ref = $this->routeReflection($route);
        if (! $ref instanceof \ReflectionMethod) {
            return [];
        }

        $controllerNames = [
            $checkExtending && is_object($route->getController()) ? get_class($route->getController()) : null, // Class from route definition
            $ref->class, // Class from reflection, where real method written
        ];
        $controllerNames = array_unique(array_filter($controllerNames));
        $annotationsClass = [];
        foreach ($controllerNames as $controllerName) {
            $annotationsClass[] = $this->classAnnotations($controllerName);
        }

        $annotationsClass = array_filter($annotationsClass);
        if (empty($annotationsClass)) {
            return [];
        }

        $annotations = $mergeExtended ? array_merge([], ...$annotationsClass) : reset($annotationsClass);

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
     * Get class annotation
     *
     * @param  string|object $class
     * @param  string        $name
     * @return BaseAnnotation|null
     */
    protected function classAnnotation($class, $name)
    {
        $annotations = $this->classAnnotations($class, $name);

        return ! empty($annotations) ? reset($annotations) : null;
    }

    /**
     * Get class annotations
     *
     * @param  string|object $class
     * @param  string|null   $name
     * @return BaseAnnotation[]
     */
    protected function classAnnotations($class, $name = null)
    {
        $className = is_string($class) ? $class : get_class($class);
        $ref = $this->reflectionClass($className);
        if (($annotations = $this->getCachedAnnotations($ref)) === null) {
            $annotations = $this->annotationReader()->getClassAnnotations($ref);
            $this->setCachedAnnotations($ref, $annotations);
        }
        if ($name === null) {
            return $annotations;
        }
        $result = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }

        return $result;
    }

    /**
     * Get class method annotations
     *
     * @param  \ReflectionMethod|array $ref
     * @param  string                  $name
     * @return BaseAnnotation|null
     */
    protected function methodAnnotation($ref, $name)
    {
        $annotations = $this->methodAnnotations($ref, $name);

        return ! empty($annotations) ? reset($annotations) : null;
    }

    /**
     * Get class method annotations
     *
     * @param  \ReflectionMethod|array $ref
     * @param  string|null             $name
     * @return BaseAnnotation[]
     */
    protected function methodAnnotations($ref, $name = null)
    {
        if (is_array($ref)) {
            $ref = $this->reflectionMethod(...$ref);
        }
        if (($annotations = $this->getCachedAnnotations($ref)) === null) {
            $annotations = $this->annotationReader()->getMethodAnnotations($ref);
            $this->setCachedAnnotations($ref, $annotations);
        }
        if ($name === null) {
            return $annotations;
        }
        $result = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof $name) {
                $result[] = $annotation;
            }
        }

        return $result;
    }

    /**
     * Get annotaion reader
     *
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

    /**
     * Get cached annotations
     *
     * @param  \ReflectionClass|\ReflectionMethod $ref
     * @return array|null
     */
    private function getCachedAnnotations($ref)
    {
        [$property, $key] = $this->getRefAnnotationsCacheKeys($ref);

        return Arr::get($this->{$property}, $key, null);
    }

    /**
     * Set annotations to cache
     *
     * @param  \ReflectionClass|\ReflectionMethod $ref
     * @param  array                              $annotations
     */
    private function setCachedAnnotations($ref, array $annotations)
    {
        [$property, $key] = $this->getRefAnnotationsCacheKeys($ref);
        Arr::set($this->{$property}, $key, $annotations);
    }

    /**
     * Get keys for annotations cache
     *
     * @param  \ReflectionClass|\ReflectionMethod $ref
     * @return array
     * @see WithAnnotationReader::$_classAnnotations
     * @see WithAnnotationReader::$_methodAnnotations
     */
    private function getRefAnnotationsCacheKeys($ref)
    {
        if ($ref instanceof \ReflectionMethod) {
            $keys = ['_methodAnnotations', $ref->class . '::' . $ref->name];
        } else {
            $keys = ['_classAnnotations', $ref->name];
        }

        return $keys;
    }
}
