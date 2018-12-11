<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Routing\Route;

/**
 * Trait WithRouteReflections
 * @package DigitSoft\Swagger\Parser
 */
trait WithRouteReflections
{
    protected $reflections = [];

    /**
     * Get route method reflection
     * @param Route $route
     * @return \ReflectionMethod
     */
    protected function routeReflection(Route $route)
    {
        $controller = $route->getController();
        $method = $route->getActionMethod();
        return $this->reflectionMethod($controller, $method);
    }

    /**
     * Get method reflection
     * @param string|object $class
     * @param string        $method
     * @return \ReflectionMethod
     */
    protected function reflectionMethod($class, $method)
    {
        $refClass = $this->reflectionClass($class);
        $methodName = $refClass->name . '::' . $method;
        if (!isset($this->reflections[$methodName])) {
            $this->reflections[$methodName] = $refClass->getMethod($method);
        }
        return $this->reflections[$methodName];
    }

    /**
     * Get class reflection
     * @param string|object $class
     * @return \ReflectionClass
     */
    protected function reflectionClass($class)
    {
        $className = is_object($class) ? get_class($class) : $class;
        if (!isset($this->reflections[$className])) {
            $this->reflections[$className] = new \ReflectionClass($className);
        }
        return $this->reflections[$className];
    }
}
