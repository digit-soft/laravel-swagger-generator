<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Routing\Route;

/**
 * Trait WithRouteReflections
 */
trait WithRouteReflections
{
    use WithReflections;

    /**
     * Get route method reflection
     *
     * @param  Route $route
     * @return \ReflectionMethod|\ReflectionFunction
     */
    protected function routeReflection(Route $route): \ReflectionMethod|\ReflectionFunction
    {
        if ($route->getActionMethod() === 'Closure') {
            $closure = $route->getAction('uses');

            return $this->reflectionClosure($closure);
        }
        $controller = $route->getController();
        $method = $route->getActionMethod();

        if ($method === get_class($controller)) {
            return $this->reflectionClass($controller)->getMethod('__invoke');
        }

        return $this->reflectionMethod($controller, $method);
    }
}
