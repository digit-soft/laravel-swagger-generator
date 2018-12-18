<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\RoutesParserEvents;
use DigitSoft\Swagger\Parser\RoutesParserHelpers;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithDocParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithRouteReflections;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RoutesParser
{
    const EVENT_START = 'parse_start';
    const EVENT_FINISH = 'parse_finish';
    const EVENT_ROUTE_PROCESSED = 'route_processed';
    const EVENT_ROUTE_SKIPPED = 'route_skipped';
    const EVENT_FORM_REQUEST_FAILED = 'route_form_request_failed';

    /**
     * @var Route[]|RouteCollection
     */
    protected $routes;
    /**
     * @var OutputStyle
     */
    protected $output;

    use WithReflections, WithRouteReflections, WithAnnotationReader, WithDocParser,
        RoutesParserHelpers, RoutesParserEvents;

    /**
     * RoutesParser constructor.
     * @param RouteCollection  $routes
     * @param OutputStyle|null $output
     */
    public function __construct(RouteCollection $routes, OutputStyle $output = null)
    {
        $this->routes = $routes;
        $this->output = $output;
    }

    /**
     * Parse routes collection
     * @return array
     */
    public function parse()
    {
        $this->trigger(static::EVENT_START);
        $paths = [];
        $only = config('swagger-generator.routes.only', []);
        $matches = config('swagger-generator.routes.matches', []);
        $documentedMethods = config('swagger-generator.routes.methods', ['GET']);
        $routeNum = 1;
        foreach ($this->routes as $route) {
            if (!$this->checkRoute($route, $matches, $only)) {
                $this->trigger(static::EVENT_ROUTE_SKIPPED, $route);
                continue;
            }
            $actionMethod = $route->getActionMethod();
            $routeData = [
                'summary' => '',
                'description' => '',
                'operationId' => $actionMethod === 'Closure' ? 'closure_' . $routeNum : $actionMethod,
            ];
            if (($security = $this->getRouteSecurity($route)) !== null) {
                $routeData['security'] = $security;
            }
            if (($tags = $this->getRouteTags($route)) !== null) {
                $routeData['tags'] = $tags;
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
            if (($responses = $this->getRouteResponses($route)) !== null) {
                $routeData['responses'] = $responses;
            }

            $path = $this->normalizeUri($route->uri(), true);
            $paths[$path] = $paths[$path] ?? [];
            foreach ($route->methods as $method) {
                if (in_array($method, $documentedMethods)) {
                    $paths[$path][strtolower($method)] = $routeData;
                }
            }
            ++$routeNum;
            $this->trigger(static::EVENT_ROUTE_PROCESSED, $route);
        }
        $this->trigger(static::EVENT_FINISH);
        return $paths;
    }

    /**
     * Get params for route
     * @param Route $route
     * @return array|null
     */
    protected function getRouteParams(Route $route)
    {
        $params = [];
        /** @var \OA\Parameter[] $paramsAnn */
        $paramsAnn = $this->routeAnnotations($route, 'OA\Parameter');
        $paramsBulkAnn = $this->routeAnnotation($route, 'OA\Parameters');
        if (!empty($paramsBulkAnn)) {
            $paramsAnn = static::merge($paramsAnn, $paramsBulkAnn->value);
        }
        $paramsDoc = $this->getRouteDocParams($route);
        if (empty($paramsAnn)) {
            $paramsAnn = [];
            foreach ($route->parameterNames() as $parameterName) {
                $required = strpos($route->uri(), '{' . $parameterName . '}') !== false;
                $type = $this->getRouteParamType($route, $parameterName);
                $paramsAnn[] = new \OA\Parameter([
                    'name' => $parameterName,
                    'type' => $type,
                    'required' => $required,
                ]);
            }
        }
        foreach ($paramsAnn as $param) {
            if (empty($param->description) && ($paramDoc = static::getArrayElemByStrKey($paramsDoc, $param->name)) !== null) {
                $param->description = $paramDoc['description'];
            }
            $params[] = $param->toArray();
        }
        return !empty($params) ? $params : null;
    }

    /**
     * Get PHPDoc 'params' for route
     * @param Route $route
     * @return array
     */
    protected function getRouteDocParams(Route $route)
    {
        $ref = $this->routeReflection($route);
        $docBlockStr = $ref->getDocComment();
        if (empty($docBlockStr)) {
            return [];
        }
        return $this->getDocTagsPropertiesDescribed($docBlockStr, 'param');
    }

    /**
     * Get responses for route
     * @param Route $route
     * @return array
     */
    protected function getRouteResponses(Route $route)
    {
        $result = [];
        /** @var \OA\Response[] $annotations */
        $annotations = $this->routeAnnotations($route, 'OA\Response');
        foreach ($annotations as $annotation) {
            $data = [
                $annotation->status => $annotation->toArray(),
            ];
            $result = static::merge($result, $data);
        }
        return $result;
    }

    /**
     * Get request body for route
     * @param Route $route
     * @return array|null
     */
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

    /**
     * Get and process FormRequest annotations and rules
     * @param string $className
     * @return array
     */
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
            $merged = static::merge(Arr::get($result, $path), $rulesData);
            Arr::set($result, $path, $merged);
        }
        return $result;
    }

    /**
     * Get FormRequest rules, process them and return described data
     * @param string $className
     * @return array
     */
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
            $this->trigger(static::EVENT_FORM_REQUEST_FAILED, $instance, $exception);
            $rulesRaw = [];
        }
        $rulesRaw = $this->normalizeFormRequestRules($rulesRaw);
        $exampleData = $this->processFormRequestRules($rulesRaw);
        return DumperYaml::describe($exampleData);
    }

    /**
     * Normalize FormRequest rules array
     * @param array $rules
     * @return array
     */
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

    /**
     * Process rules obtained from FromRequest class and return data examples.
     *
     * @param array $rules
     * @return array
     */
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
                if (($example = DumperYaml::getExampleValueByRule($ruleName, $key)) !== null) {
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
     * Get form request annotations (\OA\RequestBody)
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
            $request = static::merge($request, $annotation->toArray());
        }
        return $request;
    }

    /**
     * Check that route must be processed
     * @param Route      $route
     * @param array      $matches
     * @param array|null $only
     * @return bool
     */
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

    /**
     * Get route tag names
     * @param Route $route
     * @return string[]
     */
    protected function getRouteTags(Route $route)
    {
        $methodRef = $this->routeReflection($route);
        $tags = [];
        if ($methodRef instanceof \ReflectionMethod) {
            $annotations = $this->routeAnnotations($route, 'OA\Tag');
            foreach ($annotations as $annotation) {
                $tags[] = $annotation->name;
            }
            if (empty($tags)) {
                $controllerName = explode('\\', $methodRef->class);
                $tags[] = last($controllerName);
            }
        }
        return $tags;
    }

    /**
     * Get route security definitions
     * @param Route $route
     * @return array|null
     */
    protected function getRouteSecurity(Route $route)
    {
        $methodRef = $this->routeReflection($route);
        $results = [];
        if ($methodRef instanceof \ReflectionMethod) {
            /** @var \OA\Secured[] $annotations */
            $annotations = $this->routeAnnotations($route, 'OA\Secured');
            foreach ($annotations as $annotation) {
                $results[] = $annotation->toArray();
            }
        }
        return !empty($results) ? $results : null;
    }

    /**
     * Get route summary
     * @param Route $route
     * @return string|null
     */
    protected function getRouteSummary($route)
    {
        $methodRef = $this->routeReflection($route);
        if (($docComment = $methodRef->getDocComment()) !== false) {
            $docblock = $this->getDocFactory()->create($docComment);
            return $docblock->getSummary();
        }
        return null;
    }

    /**
     * Get description of route
     * @param Route $route
     * @return string|null
     */
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
     * Get route param type by matching to regex
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
}
