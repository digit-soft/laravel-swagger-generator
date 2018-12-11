<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithRouteReflections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\RouteCompiler;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class RoutesParser
{
    /**
     * @var Route[]|RouteCollection
     */
    protected $routes;

    protected $docFactory;

    use WithRouteReflections, WithAnnotationReader;

    /**
     * RoutesParser constructor.
     * @param RouteCollection $routes
     */
    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    public function parse()
    {
        $paths = [];
        $only = config('swagger-generator.routes.only', []);
        $matches = config('swagger-generator.routes.matches', []);
        $documentedMethods = config('swagger-generator.routes.methods', ['GET']);
        foreach ($this->routes as $route) {
            if (!$this->checkRoute($route, $matches, $only)) {
                continue;
            }
            $routeData = [
                'summary' => '',
                'description' => '',
                'operationId' => $route->getActionMethod(),
            ];
            if (($tag = $this->getRouteTag($route)) !== null) {
                $routeData['tags'] = [$tag];
            }
            if (($summary = $this->getRouteSummary($route)) !== null) {
                $routeData['summary'] = $summary;
            }
            if (($description = $this->getRouteDescription($route)) !== null) {
                $routeData['description'] = $description;
            }
            if (($params = $this->getRouteParams($route)) !== null) {
                $routeData['parameters'] = $params;
            }
            if (($request = $this->getRouteRequest($route)) !== null) {
                $routeData['requestBody'] = $request;
            }

            if ($route->uri === 'api/v1.0/users/{user}') {
                //dump($route, $route->getActionMethod());
            }

            //dd($controller, $method);
            $path = $this->normalizeUri($route->uri());
            $paths[$path] = $paths[$path] ?? [];
            foreach ($route->methods as $method) {
                if (in_array($method, $documentedMethods)) {
                    $paths[$path][strtolower($method)] = $routeData;
                }
            }
        }
        return $paths;
    }

    protected function getRouteParams(Route $route)
    {
        $params = [];
        /** @var \OA\Parameter[] $paramsAnn */
        $paramsAnn = $this->routeAnnotations($route, 'OA\Parameter');
        if (empty($paramsAnn)) {
            $paramsAnn = [];
            foreach ($route->parameterNames() as $parameterName) {
                $type = $this->getRouteParamType($route, $parameterName);
                $paramsAnn[] = new \OA\Parameter(['name' => $parameterName, 'type' => $type]);
            }
        }
        foreach ($paramsAnn as $param) {
            $params[$param->name] = $param->toArray();
        }
        return !empty($params) ? $params : null;
    }

    protected function getRouteRequest(Route $route)
    {
        $ref = $this->routeReflection($route);
        $request = null;
        $stdTypes = ['int', 'integer', 'string', 'float', 'array', 'bool', 'boolean'];
        foreach ($ref->getParameters() as $parameter) {
            if ($parameter->hasType() && ($type = $parameter->getType()->getName()) && !in_array($type, $stdTypes)) {
                if (class_exists($type) && isset(class_parents($type)[FormRequest::class])) {
                    $this->getParamsFromFormRequest($type);
                    dd($type);
                }
            }
        }
        dump($ref->getParameters());
    }

    protected function getParamsFromFormRequest($className)
    {
        $rulesData = $this->parseFormRequestRules($className);
        $annotationsData = $this->parseFormRequestAnnotations($className);

        dd($rulesData, $annotationsData);
        $ref = $this->reflectionClass($className);
        $annotations = $this->classAnnotations($className, 'OA\RequestBody');
        //dd($annotations);
        $requests = [];
        /** @var \OA\RequestBody $annotation */
        foreach ($annotations as $annotation) {
            $requests[$annotation->contentType] = $annotation->toArray();
        }
        //$annotation = $this->annotationReader()->getClassAnnotation($ref, 'OA\RequestBody');
        //dd($requests);
        /** @var FormRequest $instance */
        $instance = new $className;
        $rulesRaw = $instance->rules();
        $rules = [];
        $intRules = ['integer'];
        $floatRules = ['numeric'];
        foreach ($rulesRaw as $key => $row) {
            if (is_string($row)) {
                $row = explode('|', $row);
            }
            $row = array_filter($row, function ($value) { return is_string($value); });
            $type = 'string';
            $isUrl = in_array('url', $row);
            if (!empty(array_intersect($intRules, $row))) {
                $type = 'integer';
            }
            if (!empty(array_intersect($floatRules, $row))) {
                $type = 'float';
            }
            if (in_array('array', $row)) {
                $type = 'array';
            }

            $rules[$key] = [
                'required' => in_array('required', $row) && !in_array('nullable', $row),
                'type' => $type,
            ];
            $example = DumperYaml::getExampleValue($type);
            if (($example = DumperYaml::getExampleValue($type)) !== null) {
                $rules[$key]['example'] = $example;
            }
            //dd($row);
        }
        dd($rules);
    }

    protected function parseFormRequestRules($className)
    {
        /** @var FormRequest $instance */
        $instance = new $className;
        if (!method_exists($instance, 'rules')) {
            return [];
        }
        $rulesRaw = $instance->rules();
        $exampleData = [];
        foreach ($rulesRaw as $key => $row) {
            if (is_string($row)) {
                $row = explode('|', $row);
            }
            $row = array_filter($row, function ($value) { return is_string($value); });
            foreach ($row as $ruleName) {
                if (($example = DumperYaml::getExampleValueByRule($ruleName)) !== null) {
                    $exampleData[$key] = $example;
                }
            }
        }
        return DumperYaml::describe($exampleData);
    }

    /**
     * Get form request annotations
     * @param string $className
     * @return array
     */
    protected function parseFormRequestAnnotations($className)
    {
        $annotations = $this->classAnnotations($className, 'OA\RequestBody');
        $requests = [];
        /** @var \OA\RequestBody $annotation */
        foreach ($annotations as $annotation) {
            $requests[$annotation->contentType] = $annotation->toArray();
        }
        return $requests;
    }

    protected function normalizeUri($uri)
    {
        $uri = '/' . ltrim($uri, '/');
        $uri = str_replace('?}', '}', $uri);

        return $uri;
    }

    protected function checkRoute(Route $route, $matches = [], $only = null)
    {
        $uri = '/' . ltrim($route->uri, '/');
        $only = $only ?? config('swagger-generator.routes.only', []);
        $matches = $matches ?? config('swagger-generator.routes.matches', []);
        if (!empty($only) && in_array($uri, $only)) {
            return true;
        }
        foreach ($matches as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    protected function getRouteTag(Route $route)
    {
        $methodRef = $this->routeReflection($route);
        $annotation = $this->annotationReader()->getMethodAnnotation($methodRef, 'OA\Tag');
        if ($annotation instanceof \OA\Tag) {
            return $annotation->name;
        }
        return null;
    }

    protected function getRouteSummary($route)
    {
        $methodRef = $this->routeReflection($route);
        if (($docComment = $methodRef->getDocComment()) !== false) {
            $docblock = $this->getDocFactory()->create($docComment);
            return $docblock->getSummary();
        }
        return null;
    }

    protected function getRouteDescription($route)
    {
        $methodRef = $this->routeReflection($route);
        if (($docComment = $methodRef->getDocComment()) !== false) {
            $docblock = $this->getDocFactory()->create($docComment);
            return $docblock->getDescription()->__toString();
        }
        return null;
    }

    /**
     * @return \phpDocumentor\Reflection\DocBlockFactory
     */
    protected function getDocFactory()
    {
        if ($this->docFactory === null) {
            $this->docFactory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        }
        return $this->docFactory;
    }

    /**
     * @param Route  $route
     * @param string $paramName
     * @param string $default
     * @return mixed|string
     */
    protected function getRouteParamType(Route $route, $paramName, $default = 'integer')
    {
        $pattern = $route->wheres[$paramName] ?? null;
        if ($pattern === null) {
            return $default;
        }
        $tests = [
            'integer' => 1,
            'float' => 2.55,
        ];
        $types = [];
        foreach ($tests as $type => $value) {
            if (preg_match('/^' . $pattern . '$/', $value)) {
                $types[] = $type;
            }
        }
        return count($types) > 1 ? $default : reset($types);
    }

    protected static function paramPatternTypes()
    {
        return [
            '[0-9]+' => 'integer',
        ];
    }
}
