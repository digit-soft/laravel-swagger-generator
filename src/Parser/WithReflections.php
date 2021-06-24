<?php

namespace DigitSoft\Swagger\Parser;

/**
 * Trait WithRouteReflections
 */
trait WithReflections
{
    /**
     * @var \ReflectionClass[]|\ReflectionMethod[]
     */
    protected $reflections = [];

    /**
     * Get method reflection
     *
     * @param  string|object $class
     * @param  string        $method
     * @return \ReflectionMethod
     */
    protected function reflectionMethod($class, string $method)
    {
        $refClass = $this->reflectionClass($class);
        $methodName = $refClass->name . '::' . $method;
        if (! isset($this->reflections[$methodName])) {
            $this->reflections[$methodName] = $refClass->getMethod($method);
        }

        return $this->reflections[$methodName];
    }

    /**
     * Get method reflection
     *
     * @param  object|string $class
     * @param  string        $property
     * @return \ReflectionProperty
     */
    protected function reflectionProperty($class, string $property)
    {
        $refClass = $this->reflectionClass($class);
        $propertyName = $refClass->name . '->' . $property;
        if (! isset($this->reflections[$propertyName])) {
            $this->reflections[$propertyName] = $refClass->getProperty($property);
        }

        return $this->reflections[$propertyName];
    }

    /**
     * Get class reflection
     *
     * @param  string|object $class
     * @return \ReflectionClass
     */
    protected function reflectionClass($class)
    {
        $className = is_object($class) ? get_class($class) : $class;
        if (! isset($this->reflections[$className])) {
            $this->reflections[$className] = new \ReflectionClass($className);
        }

        return $this->reflections[$className];
    }

    /**
     * Get closure reflection
     *
     * @param  \Closure $closure
     * @return \ReflectionFunction
     */
    protected function reflectionClosure($closure)
    {
        return new \ReflectionFunction($closure);
    }

    /**
     * Get method doc block
     *
     * @param  string|object $class
     * @param  string        $method
     * @return string|null
     */
    protected function docBlockMethod($class, $method)
    {
        $ref = $this->reflectionMethod($class, $method);
        $docBlock = $ref->getDocComment();

        return is_string($docBlock) ? $docBlock : null;
    }

    /**
     * Get class doc block
     *
     * @param  string|object $class
     * @return string|null
     */
    protected function docBlockClass($class)
    {
        $ref = $this->reflectionClass($class);
        $docBlock = $ref->getDocComment();

        return is_string($docBlock) ? $docBlock : null;
    }
}
