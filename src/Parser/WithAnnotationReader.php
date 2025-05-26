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
    protected Reader $_annotationReader;
    /** @var \OA\BaseAnnotation[][] */
    private array $_classAnnotations = [];
    /** @var \OA\BaseAnnotation[][] */
    private array $_methodAnnotations = [];

    /**
     * Get route controller method annotation
     *
     * @param  Route  $route
     * @param  string $name
     * @return BaseAnnotation|null
     */
    protected function routeAnnotation(Route $route, string $name = \OA\Tag::class): ?BaseAnnotation
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
    protected function routeAnnotations(Route $route, ?string $name = null) :array
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
    protected function controllerAnnotations(Route $route, ?string $name = null, bool $checkExtending = false, bool $mergeExtended = false): array
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
    protected function classAnnotation($class, string $name): ?BaseAnnotation
    {
        $annotations = $this->classAnnotations($class, $name);

        return ! empty($annotations) ? reset($annotations) : null;
    }

    /**
     * Get class annotations
     *
     * @param  string|object $class
     * @param  string|null   $name
     * @return \OA\BaseAnnotation[]
     */
    protected function classAnnotations($class, ?string $name = null): array
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
    protected function methodAnnotation($ref, string $name): ?BaseAnnotation
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
    protected function methodAnnotations($ref, ?string $name = null): array
    {
        if (is_array($ref)) {
            $ref = $this->reflectionMethod(...$ref);
        }
        if (! $ref instanceof \ReflectionMethod) {
            return [];
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
     * Get annotation reader.
     *
     * @return \Doctrine\Common\Annotations\Reader
     */
    protected function annotationReader(): Reader
    {
        if (! isset($this->_annotationReader)) {
            AnnotationRegistry::registerLoader('class_exists');
            $ignored = config('swagger-generator.ignoredAnnotationNames', []);
            foreach ($ignored as $item) {
                AnnotationReader::addGlobalIgnoredName($item);
            }
            $this->_annotationReader = new AnnotationReader();
        }

        return $this->_annotationReader;
    }

    /**
     * Get cached annotations
     *
     * @param  \ReflectionClass|\ReflectionMethod $ref
     * @return array|null
     */
    private function getCachedAnnotations($ref): ?array
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
    private function setCachedAnnotations($ref, array $annotations): void
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
    private function getRefAnnotationsCacheKeys($ref): array
    {
        if ($ref instanceof \ReflectionMethod) {
            $keys = ['_methodAnnotations', $ref->class . '::' . $ref->name];
        } else {
            $keys = ['_classAnnotations', $ref->name];
        }

        return $keys;
    }
}
