<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Routing\Route;

/**
 * Trait WithRouteReflections
 * @package DigitSoft\Swagger\Parser
 * @mixin WithReflections
 */
trait WithRouteReflections
{
    /**
     * Get route method reflection
     * @param Route $route
     * @return \ReflectionMethod|\ReflectionFunction
     */
    protected function routeReflection(Route $route)
    {
        if ($route->getActionMethod() === 'Closure') {
            $closure = $route->getAction('uses');
            return $this->reflectionClosure($closure);
        }
        $controller = $route->getController();
        $method = $route->getActionMethod();
        return $this->reflectionMethod($controller, $method);
    }
}
