<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithRouteReflections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\RouteCompiler;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
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
                if (
                    class_exists($type)
                    && isset(class_parents($type)[FormRequest::class])
                    && ($request = $this->getParamsFromFormRequest($type)) !== null
                ) {
                    break;
                }
            }
        }
        return $request;
    }

    protected function getParamsFromFormRequest($className)
    {
        $rulesData = $this->parseFormRequestRules($className);
        $annotationsData = $this->parseFormRequestAnnotations($className);

        $result = $annotationsData;
        foreach ($result['content'] as $contentType => $schema) {
            $path = 'content.' . $contentType;
            if (isset($schema['schema'])) {
                $path .= '.schema';
            }
            $merged = static::mergeArray(Arr::get($result, $path), $rulesData);
            Arr::set($result, $path, $merged);
        }
        return $result;
    }

    protected function parseFormRequestRules($className)
    {
        /** @var FormRequest $instance */
        $instance = new $className;
        if (!method_exists($instance, 'rules')) {
            return [];
        }
        try {
            $rulesRaw = $instance->rules();
        } catch (\Throwable $exception) {
            $rulesRaw = [];
        }
        $rulesRaw = $this->normalizeFormRequestRules($rulesRaw);
        $exampleData = $this->processFormRequestRules($rulesRaw);
        return DumperYaml::describe($exampleData);
    }

    protected function normalizeFormRequestRules(array $rules)
    {
        $result = [];
        $rulesExpanded = [];
        foreach ($rules as $key => $row) {
            if (strpos($key, '.') !== false) {
                Arr::set($rulesExpanded, $key, $row);
                unset($rules[$key]);
            }
        }
        $rules = array_merge($rules, $rulesExpanded);
        foreach ($rules as $key => $row) {
            if (is_object($row)) {
                continue;
            }
            if (is_string($row)) {
                $row = explode('|', $row);
            }
            if (Arr::isAssoc($row)) {
                $row = $this->normalizeFormRequestRules($row);
            } else {
                $row = array_values(array_filter($row, function ($value) { return is_string($value); }));
            }
            $result[$key] = $row;
        }
        return $result;
    }

    protected function processFormRequestRules(array $rules)
    {
        $result = [];
        foreach ($rules as $key => $row) {
            if (Arr::isAssoc($row)) {
                $result[$key] = $this->processFormRequestRules($row);
                continue;
            }
            foreach ($row as $ruleName) {
                if (strpos($ruleName, ':')) {
                    $ruleName = explode(':', $ruleName)[0];
                }
                if (($example = DumperYaml::getExampleValueByRule($ruleName)) !== null) {
                    if ($key === '*') {
                        $result = [$example];
                    } else {
                        $result[$key] = $example;
                    }
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Get form request annotations
     * @param string $className
     * @return array
     */
    protected function parseFormRequestAnnotations($className)
    {
        $annotations = $this->classAnnotations($className, 'OA\RequestBody');
        if (empty($annotations)) {
            $annotations = [new \OA\RequestBodyJson(['content' => []])];
        }
        $request = [];
        /** @var \OA\RequestBody $annotation */
        foreach ($annotations as $annotation) {
            $request = static::mergeArray($request, $annotation->toArray());
        }
        return $request;
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

    /**
     * @param array $a
     * @param array $b
     * @return array|mixed
     */
    protected static function mergeArray($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::mergeArray($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }
}
