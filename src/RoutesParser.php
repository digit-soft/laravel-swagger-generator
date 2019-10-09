<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Parser\CleanupsDescribedData;
use DigitSoft\Swagger\Parser\RoutesParserEvents;
use DigitSoft\Swagger\Parser\RoutesParserHelpers;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithDocParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithRouteReflections;
use DigitSoft\Swagger\Parser\WithVariableDescriber;
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
    const EVENT_PROBLEM_FOUND = 'problem_found';

    const COMPONENT_RESPONSE = 'responses';
    const COMPONENT_REQUESTS = 'requestBodies';
    const COMPONENT_PARAMETER = 'parameters';

    const PROBLEM_NO_RESPONSE = 'no_response';
    const PROBLEM_ROUTE_CLOSURE = 'route_closure';
    const PROBLEM_NO_DOC_CLASS = 'route_no_doc_class';
    const PROBLEM_MISSING_TAG = 'route_tag_missing';
    const PROBLEM_MISSING_PARAM = 'route_param_missing';

    /**
     * @var Route[]|RouteCollection
     */
    protected $routes;
    /**
     * @var OutputStyle
     */
    protected $output;

    protected $routeIds = [];

    protected $routeNum = 1;

    public $components = [
        'requestBodies' => [],
        'responses' => [],
        'parameters' => [],
    ];

    public $problems = [];

    use WithReflections, WithRouteReflections, WithAnnotationReader, WithDocParser,
        RoutesParserHelpers, RoutesParserEvents, CleanupsDescribedData, WithVariableDescriber;

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
        $this->routeNum = 1;
        foreach ($this->routes as $route) {
            if (!$this->checkRoute($route, $matches, $only)) {
                $this->trigger(static::EVENT_ROUTE_SKIPPED, $route);
                continue;
            }
            $ref = $this->routeReflection($route);
            if ($ref instanceof \ReflectionFunction) {
                $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_ROUTE_CLOSURE, $route);
            }
            $routeData = [
                'summary' => '',
                'description' => '',
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

            $tag = !empty($routeData['tags']) ? reset($routeData['tags']) : 'default';
            $routeData['operationId'] = $this->getRouteId($route, $tag);
            $path = $this->normalizeUri($route->uri(), true);
            $paths[$path] = $paths[$path] ?? [];
            foreach ($route->methods as $method) {
                if (in_array($method, $documentedMethods)) {
                    $paths[$path][strtolower($method)] = $routeData;
                }
            }
            ++$this->routeNum;
            $this->trigger(static::EVENT_ROUTE_PROCESSED, $route);
        }
        $this->trigger(static::EVENT_FINISH);
        return $paths;
    }

    protected function getRouteId(Route $route, $tagName = 'default')
    {
        $actionMethod = $route->getActionMethod();
        if ($actionMethod === 'Closure') {
            return 'closure_' . $this->routeNum;
        }
        $id = $actionMethod;
        $fullId = $tagName . '/' . $id;
        if (isset($this->routeIds[$fullId])) {
            $ctrlName = get_class($route->getController());
            $ctrlNameArr = explode('\\', $ctrlName);
            $ctrlBaseName = end($ctrlNameArr);
            $ctrlBaseName = Str::endsWith($ctrlBaseName, 'Controller') ? substr($ctrlBaseName, 0, -10) : $ctrlBaseName;
            $id = $ctrlBaseName . ucfirst($id);
            $fullId = $tagName . '/' . $id;
        }
        $this->routeIds[$fullId] = true;
        return $id;
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
        /** @var \OA\Parameter[] $paramsAnnCtrl */
        $paramsAnn = $this->routeAnnotations($route, 'OA\Parameter');
        if (!empty($paramsAnnGroup = $this->routeAnnotations($route, 'OA\Parameters'))) {
            foreach ($paramsAnnGroup as $paramsGroup) {
                /** @var \OA\Parameters $paramsGroup */
                $paramsAnn = array_merge($paramsAnn, $paramsGroup->parameters);
            }
            $paramsAnn = array_unique($paramsAnn, SORT_STRING);
        }
        $paramsAnnCtrl = Arr::pluck($this->controllerAnnotations($route, 'OA\Parameter'), null, 'name');
        $paramsDoc = $this->getRouteDocParams($route);
        $paramsAnnInPath = array_filter($paramsAnn, function ($param) { return $param->in === 'path'; });
        if (empty($paramsAnnInPath)) {
            $paramsAnn = Arr::pluck($paramsAnn, null, 'name');

            foreach ($route->parameterNames() as $parameterName) {
                if (isset($paramsAnn[$parameterName])) {
                    continue;
                }
                $required = strpos($route->uri(), '{' . $parameterName . '}') !== false;
                if (($paramDoc = static::getArrayElemByStrKey($paramsDoc, $parameterName)) !== null
                    && isset($paramDoc['type'])
                    && $this->describer()->isBasicType($paramDoc['type'])
                ) {
                    $type = $paramDoc['type'];
                } else {
                    $type = $this->getRouteParamType($route, $parameterName);
                }
                $paramData = [
                    'name' => $parameterName,
                    'type' => $type,
                ];
                $annotation = $paramsAnnCtrl[$parameterName] ?? (new \OA\Parameter([]))->fill($paramData);
                // Overwrite required property of annotation
                $annotation->required = $required;
                $paramsAnn[] = $annotation;
            }
        }
        foreach ($paramsAnn as $param) {
            if (empty($param->description) && ($paramDoc = static::getArrayElemByStrKey($paramsDoc, $param->name)) !== null) {
                $param->description = $paramDoc['description'];
            }
            if ($param->description === null || $param->description === '') {
                $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_MISSING_PARAM, $route, $param->name);
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
        if (empty($annotations)) {
            $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_NO_RESPONSE, $route);
        }
        foreach ($annotations as $annotation) {
            $annKey = $annotation->getComponentKey();
            if (($annotationData = $this->getComponent($annKey, static::COMPONENT_RESPONSE)) === null) {
                $annotationData = $annotation->toArray();
                if (!$annotation->hasData()) {
                    $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_NO_DOC_CLASS, $route, $annotation->content);
                }
                $this->setComponent($annotationData, $annKey, static::COMPONENT_RESPONSE);
            }
            $annotationDataRef = ['$ref' => $this->getComponentReference($annKey, static::COMPONENT_RESPONSE)];
            $annStatus = $annotation->status;
            $data = [
                $annStatus => $annKey !== null ? $annotationDataRef : $annotationData,
            ];
            if (isset($result[$annStatus])) {
                $result[$annStatus] = $this->describer()->merge($result[$annStatus], $data[$annStatus]);
            } else {
                $result = $this->describer()->merge($result, $data);
            }
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
        // Parse route annotations
        if ($request === null) {
            /** @var \OA\RequestBody[] $requestAnn */
            $requestAnn = $this->routeAnnotations($route, 'OA\RequestBody');
            if (!empty($requestAnn)) {
                $request = [];
                foreach ($requestAnn as $annotation) {
                    $request = $this->describer()->merge($request, $annotation->toArray());
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
        $classKey = $this->describer()->shortenClass($className);
        if (($result = $this->getComponent($classKey, static::COMPONENT_REQUESTS)) === null) {
            $rulesData = $this->parseFormRequestRules($className);
            $annotationsData = $this->parseFormRequestAnnotations($className);
            if (empty($annotationsData['description'])) {
                $classRef = $this->reflectionClass($className);
                $annotationsData['description'] = $this->getDocSummary($classRef->getDocComment());
            }

            $result = $annotationsData;
            foreach ($result['content'] as $contentType => $schema) {
                $path = 'content.' . $contentType;
                if (isset($schema['schema'])) {
                    $path .= '.schema';
                }
                $merged = $this->describer()->merge($rulesData, Arr::get($result, $path, []));
                static::handleIncompatibleTypeKeys($merged);
                Arr::set($result, $path, $merged);
            }
            $this->setComponent($result, $classKey, static::COMPONENT_REQUESTS);
        }
        return !empty($result) ? ['$ref' => $this->getComponentReference($classKey, static::COMPONENT_REQUESTS)] : [];
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
        list($exampleData) = $this->processFormRequestRules($rulesRaw);
        return $exampleData;
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
     * @param array       $rules
     * @param bool        $describe
     * @param string|null $parent
     * @return array
     */
    protected function processFormRequestRules(array $rules, $describe = true, $parent = null)
    {
        $result = [];
        $required = [];
        foreach ($rules as $key => $row) {
            $required[$key] = false;
            if (Arr::isAssoc($row)) {
                // Replace KEY for nested rules
                $keyToPlace = $key === "*" ? 0 : $key;
                list($result[$keyToPlace], $required[$keyToPlace]) = $this->processFormRequestRules($row, false, $key);
                continue;
            }
            foreach ($row as $ruleName) {
                if (strpos($ruleName, ':')) {
                    $ruleName = explode(':', $ruleName)[0];
                }
                $required[$key] = $ruleName === 'required' ? true : $required[$key];
                $type = null;
                $keyForExample = $key === '*' && $parent !== null ? $parent : $key;
                if (($example = $this->describer()->example($type, $keyForExample, $ruleName)) !== null) {
                    if ($key === '*') {
                        $result = [$example];
                    } else {
                        $result[$key] = $example;
                    }
                    break;
                }
            }
        }
        if ($describe) {
            $result = $this->describer()->describe($result);
            $this->applyFormRequestRequiredRules($result, $required);
        }
        return [$result, $required];
    }

    /**
     * Apply required rules to parsed and described rules
     * @param array $rules
     * @param array $required
     */
    protected function applyFormRequestRequiredRules(&$rules, $required)
    {
        foreach ($required as $key => $value) {
            if (is_array($value) && isset($rules['properties'][$key])) {
                $this->applyFormRequestRequiredRules($rules['properties'][$key], $value);
            } elseif (is_bool($value) && $value) {
                if ($key === '*') {
                    $rules['required'] = $value;
                } elseif(isset($rules['properties'][$key])) {
                    $keyToSet = 'properties.' . $key . '.required';
                    Arr::set($rules, $keyToSet, $value);
                }
            }
        }
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
            $request = $this->describer()->merge($request, $annotation->toArray());
        }
        return $request;
    }

    /**
     * Check if route is ignored by annotation
     * @param  Route $route
     * @return bool
     */
    protected function isRouteIgnored(Route $route)
    {
        if ($this->routeAnnotation($route, 'OA\Ignore') !== null || !empty($this->controllerAnnotations($route, 'OA\Ignore'))) {
            return true;
        }
        return false;
    }

    /**
     * Check that route must be processed
     *
     * @param  Route      $route
     * @param  array      $matches
     * @param  array|null $only
     * @param  null       $except
     * @return bool
     */
    protected function checkRoute(Route $route, $matches = [], $only = null, $except = null)
    {
        if ($this->isRouteIgnored($route)) {
            return false;
        }
        $uri = '/' . ltrim($route->uri, '/');
        $only = $only ?? config('swagger-generator.routes.only', []);
        $except = $except ?? config('swagger-generator.routes.not', []);
        $matches = $matches ?? config('swagger-generator.routes.matches', []);
        $matchesNot = $matchesNot ?? config('swagger-generator.routes.notMatches', []);
        if (!empty($except) && in_array($uri, $except)) {
            return false;
        }
        if (!empty($only) && in_array($uri, $only)) {
            return true;
        }
        foreach ($matchesNot as $pattern) {
            if (Str::is($pattern, $uri)) {
                return false;
            }
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
            $annotationsClass = $this->classAnnotations($methodRef->class, 'OA\Tag');
            $annotations = $this->routeAnnotations($route, 'OA\Tag');
            $annotations = $this->describer()->merge($annotationsClass, $annotations);
            foreach ($annotations as $annotation) {
                $tags[] = (string)$annotation;
            }
            if (empty($tags)) {
                $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_MISSING_TAG, $route);
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
        $description = '';
        if (($docComment = $methodRef->getDocComment()) !== false) {
            $docblock = $this->getDocFactory()->create($docComment);
            $description = $docblock->getDescription()->__toString();
        }
        // Parse description extenders
        $annotations = $this->methodAnnotations($methodRef, 'OA\DescriptionExtender');
        foreach ($annotations as $annotation) {
            if (($descriptionAnn = $annotation->__toString()) === '') {
                continue;
            }
            $description .= "\n\n" . $descriptionAnn;
        }
        return $description !== '' ? $description : null;
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
