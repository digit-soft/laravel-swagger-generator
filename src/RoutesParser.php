<?php

namespace DigitSoft\Swagger;

use OA\Parameter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Illuminate\Console\OutputStyle;
use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Routing\RouteCollection;
use DigitSoft\Swagger\Parser\WithDocParser;
use Illuminate\Foundation\Http\FormRequest;
use DigitSoft\Swagger\Parser\RoutesParserEvents;
use DigitSoft\Swagger\Parser\RoutesParserHelpers;
use DigitSoft\Swagger\Parser\WithAnnotationReader;
use DigitSoft\Swagger\Parser\WithRouteReflections;
use DigitSoft\Swagger\Parser\CleanupsDescribedData;
use DigitSoft\Swagger\Parser\WithVariableDescriber;

class RoutesParser
{
    use WithRouteReflections, WithAnnotationReader, WithDocParser,
        RoutesParserHelpers, RoutesParserEvents, CleanupsDescribedData, WithVariableDescriber;

    const EVENT_START = 'parse_start';
    const EVENT_FINISH = 'parse_finish';
    const EVENT_ROUTE_PROCESSED = 'route_processed';
    const EVENT_ROUTE_SKIPPED = 'route_skipped';
    const EVENT_FORM_REQUEST_FAILED = 'route_form_request_failed';
    const EVENT_PROBLEM_FOUND = 'problem_found';

    const COMPONENT_RESPONSE = 'responses';
    const COMPONENT_REQUESTS = 'requestBodies';
    const COMPONENT_PARAMETER = 'parameters';
    const COMPONENT_OBJECTS = 'x-objects';
    const COMPONENT_SCHEMAS = 'schemas';

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

    /**
     * RoutesParser constructor.
     *
     * @param  RouteCollection  $routes
     * @param  OutputStyle|null $output
     */
    public function __construct(RouteCollection $routes, ?OutputStyle $output = null)
    {
        $this->routes = $routes;
        $this->output = $output;
    }

    /**
     * Parse routes collection
     *
     * @return array
     */
    public function parse(): array
    {
        $this->trigger(static::EVENT_START);
        $paths = [];
        $only = config('swagger-generator.routes.only', []);
        $except = config('swagger-generator.routes.not', []);
        $matches = config('swagger-generator.routes.matches', []);
        $matchesNot = config('swagger-generator.routes.notMatches', []);
        $documentedMethods = config('swagger-generator.routes.methods', ['GET']);
        $this->routeNum = 1;
        foreach ($this->routes as $route) {
            if (! $this->checkRoute($route, $matches, $matchesNot, $only, $except)) {
                $this->trigger(static::EVENT_ROUTE_SKIPPED, $route);
                continue;
            }
            $ref = $this->routeReflection($route);
            if ($ref instanceof \ReflectionFunction) {
                $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_ROUTE_CLOSURE, $route);
            }
            $this->parseRoute($paths, $route, $documentedMethods, $this->routeNum);
            ++$this->routeNum;
            $this->trigger(static::EVENT_ROUTE_PROCESSED, $route);
        }
        $this->trigger(static::EVENT_FINISH);

        return $paths;
    }

    /**
     * Parse one route.
     *
     * @param  array                     $data
     * @param  \Illuminate\Routing\Route $route
     * @param  array                     $documentedMethods
     * @param  int                       $num
     * @return array
     */
    protected function parseRoute(array &$data, Route $route, array $documentedMethods, int $num = 1): array
    {
        $routeWoBody = ! empty(array_intersect(['GET', 'HEAD'], $route->methods));
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
        if (($request = $this->getRouteRequest($route, $routeWoBody)) !== null) {
            if ($routeWoBody) {
                $routeData['parameters'] = isset($routeData['parameters']) ? array_merge($routeData['parameters'], $request) : $request;
            } else {
                $routeData['requestBody'] = $request;
            }
        }
        if (($responses = $this->getRouteResponses($route)) !== null) {
            $routeData['responses'] = $responses;
        }

        $tag = ! empty($routeData['tags']) ? reset($routeData['tags']) : 'default';
        $routeData['operationId'] = $this->getRouteId($route, $tag);
        $path = $this->normalizeUri($route->uri(), true);
        $methods = array_intersect($route->methods, $documentedMethods);
        $dataRows = [
            [$path, $methods, $routeData]
        ];
        // Add multiple definitions for routes with optional parameters
        if (str_contains($route->uri(), '?')) {
            $paramsOptional = [];
            preg_match_all('/\{(?P<parameters>[a-z0-9_-]+)\?\}/i', $route->uri(), $paramsOptional);
            $paramsOptional = array_reverse($paramsOptional['parameters']);
            $pathLast = $path;
            $paramNamesRemoved = [];
            $numCopy = 1;
            foreach ($paramsOptional as $paramName) {
                $paramNamesRemoved[] = $paramName;
                $pathNew = rtrim((string) preg_replace('/\/?\{' . $paramName . '\}/', '', $pathLast), '/');
                $paramsCurrent = array_filter($params, fn ($p) => $p['in'] !== 'path' || ! in_array($p['name'], $paramNamesRemoved, true));
                $routeDataCurrent = array_merge($routeData, ['parameters' => $paramsCurrent, 'operationId' => $routeData['operationId'] . '.cp-' . $numCopy]);

                $dataRows[] = [$pathNew, $methods, $routeDataCurrent];
                $pathLast = $pathNew;
                $numCopy++;
            }
        }
        // Populate target array with parsed data
        foreach ($dataRows as $row) {
            [$rPath, $rMethods, $rData] = $row;
            foreach ($rMethods as $m) {
                $data[$rPath][strtolower((string) $m)] = $rData;
            }
        }

        return [$path, $methods, $routeData];
    }

    /**
     * Get ID for given route.
     *
     * @param  \Illuminate\Routing\Route $route
     * @param  string|null               $tagName
     * @return string
     */
    protected function getRouteId(Route $route, ?string $tagName = 'default'): string
    {
        $actionMethod = $route->getActionMethod();
        if ($actionMethod === 'Closure') {
            return 'closure_' . $this->routeNum;
        }
        if (($id = $route->getName()) === null) {
            $ctrlName = get_class($route->getController());
            $ctrlNameArr = explode('\\', $ctrlName);
            $ctrlBaseName = end($ctrlNameArr);
            $ctrlBaseName = Str::endsWith($ctrlBaseName, 'Controller') ? substr($ctrlBaseName, 0, -10) : $ctrlBaseName;
            $id = Str::snake($ctrlBaseName . ucfirst($actionMethod));
        }
        if (isset($this->routeIds[$id])) {
            $id .= '_' . $this->routeNum;
        }
        $this->routeIds[$id] = true;

        return $id;
    }

    /**
     * Get params for route
     *
     * @param  \Illuminate\Routing\Route $route
     * @return array|null
     */
    protected function getRouteParams(Route $route): ?array
    {
        $params = [];
        /** @var \OA\Parameter[] $paramsAnn */
        /** @var \OA\Parameter[] $paramsAnnCtrl */
        $paramsAnn = $this->routeAnnotations($route, \OA\Parameter::class);
        if (! empty($paramsAnnGroup = $this->routeAnnotations($route, \OA\Parameters::class))) {
            foreach ($paramsAnnGroup as $paramsGroup) {
                /** @var \OA\Parameters $paramsGroup */
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $paramsAnn = array_merge($paramsAnn, $paramsGroup->parameters);
            }
            $paramsAnn = array_unique($paramsAnn, SORT_STRING);
        }
        $paramsAnnCtrl = Arr::pluck($this->controllerAnnotations($route, \OA\Parameter::class), null, 'name');
        $paramsDoc = $this->getRouteDocParams($route);
        $paramsAnnInPath = array_filter($paramsAnn, function ($param) {
            return $param->in === 'path';
        });
        if (empty($paramsAnnInPath)) {
            $paramsAnn = Arr::pluck($paramsAnn, null, 'name');

            foreach ($route->parameterNames() as $parameterName) {
                if (isset($paramsAnn[$parameterName])) {
                    continue;
                }
                $required = str_contains($route->uri(), '{' . $parameterName . '}');
                if (($paramDoc = static::getArrayElemByStrKey($paramsDoc, $parameterName)) !== null
                    && isset($paramDoc['type'])
                    && $this->describer()->isBasicType($paramDoc['type'])
                ) {
                    $type = $paramDoc['type'];
                } else {
                    $type = $this->getRouteParamType($route, $parameterName, 'string');
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

        return ! empty($params) ? $params : null;
    }

    /**
     * Get PHPDoc 'params' for route
     *
     * @param  \Illuminate\Routing\Route $route
     * @return array
     */
    protected function getRouteDocParams(Route $route): array
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
     *
     * @param  \Illuminate\Routing\Route $route
     * @return array
     */
    protected function getRouteResponses(Route $route): array
    {
        $result = [];
        /** @var \OA\Response[] $annotations */
        $annotations = $this->routeAnnotations($route, \OA\Response::class);
        if (empty($annotations)) {
            $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_NO_RESPONSE, $route);
        }
        foreach ($annotations as $annotation) {
            $annKey = $annotation->getComponentKey();
            $annotationData = $annKey !== null ? $this->getComponent($annKey, static::COMPONENT_RESPONSE) : null;
            if ($annotationData === null) {
                $annotationData = $annotation->toArray();
                if (! $annotation->hasData()) {
                    $this->trigger(static::EVENT_PROBLEM_FOUND, static::PROBLEM_NO_DOC_CLASS, $route, $annotation->content);
                }
                if ($annKey !== null) {
                    $this->setComponent($annotationData, $annKey, static::COMPONENT_RESPONSE);
                }
            }
            $annStatus = $annotation->status;
            $data = [
                $annStatus => $annKey !== null
                    ? ['$ref' => $this->getComponentReference($annKey, static::COMPONENT_RESPONSE)]
                    : $annotationData,
            ];
            if (isset($result[$annStatus])) {
                $result[$annStatus] = $this->describer()->merge($result[$annStatus], $data[$annStatus]);
                // Leave only `$ref` (reference)
                if (isset($result[$annStatus]['$ref'])) {
                    $result[$annStatus] = Arr::only($result[$annStatus], ['$ref']);
                }
            } else {
                $result = $this->describer()->merge($result, $data);
            }
        }

        return $result;
    }

    /**
     * Get request body for route
     *
     * @param  \Illuminate\Routing\Route $route         Route to get request data
     * @param  bool                      $asQueryParams Return request body as query parameters
     * @return array|null
     */
    protected function getRouteRequest(Route $route, bool $asQueryParams = false): ?array
    {
        $ref = $this->routeReflection($route);
        $request = null;
        $stdTypes = ['int', 'integer', 'string', 'float', 'array', 'bool', 'boolean'];
        foreach ($ref->getParameters() as $parameter) {
            if (
                $parameter->hasType()
                && ($type = $parameter->getType()?->getName()) !== null
                && ! in_array($type, $stdTypes, true)
                && class_exists($type)
            ) {
                $isFormRequest = isset(class_parents($type)[FormRequest::class]);
                $isDto = isset(class_parents($type)[\Spatie\LaravelData\Data::class]);
                if (! ($isFormRequest || $isDto) || $this->hasIgnoreAnnotation($type)) {
                    continue;
                }
                $request = $isFormRequest
                    ? $this->getParamsFromFormRequest($type, $asQueryParams)
                    : $this->getParamsFromFormRequest($type, $asQueryParams, true);
                // Request data parsed successfully
                if ($request !== null) {
                    break;
                }
            }
        }
        // Parse route annotations
        if ($request === null) {
            /** @var \OA\RequestBody[] $requestAnn */
            $requestAnn = $this->routeAnnotations($route, \OA\RequestBody::class);
            if (! empty($requestAnn)) {
                $request = [];
                foreach ($requestAnn as $annotation) {
                    $request = $this->describer()->merge($request, $annotation->toArray());
                }
            }
        }

        if ($asQueryParams) {
            return $request !== null ? $this->convertRequestBodyIntoQueryParams($request) : $request;
        }

        return $request;
    }

    /**
     * Get and process FormRequest or DTO annotations and rules
     *
     * @param  string $className
     * @param  bool   $asRaw
     * @param  bool   $asDto Parse DTO, not FromRequest
     * @return array
     */
    protected function getParamsFromFormRequest(string $className, bool $asRaw = false, bool $asDto = false): array
    {
        $classKey = $this->describer()->shortenClass($className);
        if ($asRaw || ($result = $this->getComponent($classKey, static::COMPONENT_REQUESTS)) === null) {
            $rulesData = $asDto ? $this->parseDtoRequestRules($className) : $this->parseFormRequestRules($className);
            $annotationsData = $this->parseFormRequestAnnotations($className);
            if (empty($annotationsData['description'])) {
                $classRef = $this->reflectionClass($className);
                $descriptionRaw = $classRef->getDocComment();
                $annotationsData['description'] = is_string($descriptionRaw) ? $this->getDocSummary($descriptionRaw) : '';
            }

            $result = $annotationsData;
            foreach ($result['content'] as $contentType => $schema) {
                $path = 'content.' . $contentType;
                if (isset($schema['schema'])) {
                    $path .= '.schema';
                }
                $merged = $this->describer()->mergeUnique($rulesData, Arr::get($result, $path, []));
                static::handleIncompatibleTypeKeys($merged);
                Arr::set($result, $path, $merged);
            }
            // Do not set component if `asRaw` is true
            if (! $asRaw) {
                $this->setComponent($result, $classKey, static::COMPONENT_REQUESTS);
            }
        }

        if ($asRaw) {
            return $result ?? [];
        }

        return ! empty($result) ? ['$ref' => $this->getComponentReference($classKey, static::COMPONENT_REQUESTS)] : [];
    }

    /**
     * Convert request body array into query parameters.
     *
     * @param  array $body Request body array
     * @return array|null
     */
    protected function convertRequestBodyIntoQueryParams(array $body): ?array
    {
        if (! isset($body['content'])) {
            return null;
        }
        $params = [];
        foreach ($body['content'] as $contentType => $bodyByContentType) {
            if (empty($bodyByContentType['schema']['properties'])) {
                continue;
            }
            $properties = $bodyByContentType['schema']['properties'];
            $requiredProperties = $bodyByContentType['schema']['required'] ?? [];
            foreach ($properties as $property => $row) {
                $type = $row['type'] ?? null;
                $row['required'] = in_array($property, $requiredProperties, true);
                $paramName = $property;
                // Parse objects
                if ($type === Variable::SW_TYPE_OBJECT) {
                    $paramsPlain = $this->convertRequestBodyObjectToPlainParamsForQuery($row, $paramName);
                    $params = array_merge($params, $paramsPlain);
                    continue;
                }
                // Modify array
                if ($type === Variable::SW_TYPE_ARRAY) {
                    $paramName .= '[]';
                    $typeItems = $row['items'] ?? Variable::SW_TYPE_STRING;
                    $typeItems = is_array($typeItems) ? $typeItems['type'] ?? Variable::SW_TYPE_STRING : $typeItems;
                    $row = array_merge($row, ['items' => $typeItems]);
                }
                $param = new Parameter(array_merge($row, ['in' => 'query', 'name' => $paramName]));
                $params[$property] = $param->toArray();
            }
        }

        return ! empty($params) ? array_values($params) : null;
    }

    /**
     * Extract a plain list of properties from the object to build the query parameters.
     *
     * @param  array       $description
     * @param  string|null $prefix
     * @param  array       $results
     * @return array
     */
    protected function convertRequestBodyObjectToPlainParamsForQuery(array $description, ?string $prefix = null, array &$results = []): array
    {
        $properties = $description['properties'] ?? [];
        foreach ($properties as $property => $row) {
            $propertyType = $row['type'] ?? Variable::SW_TYPE_STRING;
            $propertyFullName = $prefix !== null ? $prefix . '[' . $property . ']' : $property;
            if ($propertyType === Variable::SW_TYPE_OBJECT) {
                $this->convertRequestBodyObjectToPlainParamsForQuery($row, $propertyFullName, $results);
                continue;
            }
            $results[$propertyFullName] = (new Parameter(array_merge($row, ['in' => 'query', 'type' => $propertyType, 'name' => $propertyFullName])))->toArray();
        }

        return $results;
    }

    /**
     * Get FormRequest rules, process them and return described data
     *
     * @param  string $className
     * @return array Described request by validation rules + required attributes
     */
    protected function parseFormRequestRules(string $className): array
    {
        /** @var FormRequest $instance */
        $instance = new $className;
        if (! method_exists($instance, 'rules')) {
            return [];
        }
        try {
            $rulesRaw = $instance->rules();
            $ignoreParams = array_map(fn (\OA\RequestParamIgnore $a) => $a->name, $this->classAnnotations($className, \OA\RequestParamIgnore::class));
            $rulesRaw = ! empty($ignoreParams) ? Arr::except($rulesRaw, $ignoreParams) : $rulesRaw;
            $labels = $instance->attributes();
        } catch (\Throwable $exception) {
            $this->trigger(static::EVENT_FORM_REQUEST_FAILED, $instance, $exception);
            $rulesRaw = [];
            $labels = [];
        }
        $rulesNormalized = $this->normalizeFormRequestRules($rulesRaw);
        [$exampleData, , $required] = $this->processFormRequestRules($rulesNormalized, $rulesRaw, $labels);
        $requiredAttributes = array_values(array_keys(array_filter($required, function ($v, $k) {
            return is_string($k) && (
                    (is_bool($v) && $v) || (is_array($v) && ! empty($v['__self']))
                );
        }, ARRAY_FILTER_USE_BOTH)));
        if (! empty($requiredAttributes)) {
            $exampleData['required'] = $requiredAttributes;
        }

        return $exampleData;
    }

    /**
     * Get rules from DataTransferObjects, process them and return described data
     *
     * @param  string $className DTO class name
     * @return array Described request by validation rules + required attributes
     */
    protected function parseDtoRequestRules(string $className): array
    {
        /** @var \Spatie\LaravelData\Data $className */
        $rulesRaw = $className::getValidationRules($className::empty());
        try {
            // $rulesRaw = $instance->rules();
            $ignoreParams = array_map(fn (\OA\RequestParamIgnore $a) => $a->name, $this->classAnnotations($className, \OA\RequestParamIgnore::class));
            $rulesRaw = ! empty($ignoreParams) ? Arr::except($rulesRaw, $ignoreParams) : $rulesRaw;
            $labels = method_exists($className, 'attributes') ? $className::attributes() : [];
        } catch (\Throwable $exception) {
            $this->trigger(static::EVENT_FORM_REQUEST_FAILED, $className, $exception);
            $rulesRaw = [];
            $labels = [];
        }
        $rulesNormalized = $this->normalizeFormRequestRules($rulesRaw);
        [$exampleData, , $required] = $this->processFormRequestRules($rulesNormalized, $rulesRaw, $labels);
        $requiredAttributes = array_values(array_keys(array_filter($required, function ($v, $k) {
            return is_string($k) && (
                    (is_bool($v) && $v) || (is_array($v) && ! empty($v['__self']))
                );
        }, ARRAY_FILTER_USE_BOTH)));
        if (! empty($requiredAttributes)) {
            $exampleData['required'] = $requiredAttributes;
        }

        return $exampleData;
    }

    /**
     * Normalize FormRequest rules array
     *
     * @param  array $rules
     * @return array
     */
    protected function normalizeFormRequestRules(array $rules): array
    {
        $result = [];
        $rulesExpanded = [];
        foreach ($rules as $key => $row) {
            if (str_contains((string) $key, '.')) {
                Arr::set($rulesExpanded, $key, $row);
                unset($rules[$key]);
            }
        }
        $this->normalizeFormRequestRulesExpanded($rulesExpanded);
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
                array_walk($row, function (&$rule) { $rule = is_object($rule) && $rule instanceof \Stringable ? (string)$rule : $rule; });
                $row = array_values(array_filter($row, function ($value) { return is_string($value); }));
            }
            $result[$key] = $row;
        }

        return $result;
    }

    /**
     * Remove validation rules in objects which were put there with some validation rules.
     *
     * Like:
     * [
     *      "videos" => ["array"],
     *      "videos.*" => ["array"],
     *      "videos.*.url" => ["string"],
     * ]
     *
     * @param  array $rules
     * @return void
     */
    protected function normalizeFormRequestRulesExpanded(array &$rules)
    {
        if (Arr::isAssoc($rules)) {
            if (isset($rules[0])) {
                $rules = array_filter($rules, fn($k) => ! is_int($k), ARRAY_FILTER_USE_KEY);
            }
            foreach ($rules as &$children) {
                if (is_array($children)) {
                    $this->normalizeFormRequestRulesExpanded($children);
                }
            }
        }
    }

    /**
     * Process rules obtained from FromRequest class and return data examples.
     *
     * @param  array       $rules    Rulex processed and nested
     * @param  array       $rulesRaw Rules raw
     * @param  array       $labels
     * @param  bool        $describe
     * @param  string|null $parent
     * @param  string|null $parentFullPath
     * @return array
     */
    protected function processFormRequestRules(array $rules, array $rulesRaw, array $labels = [], bool $describe = true, ?string $parent = null, ?string $parentFullPath = null): array
    {
        $resultAdditional = [];
        $result = [];
        $required = [];
        foreach ($rules as $key => $row) {
            $required[$key] = false;
            $fullPath = (isset($parentFullPath) ? $parentFullPath . '.' : '') . $key;
            if (Arr::isAssoc($row)) {
                // Replace KEY for nested rules
                $keyToPlace = $key === "*" ? 0 : $key;
                [$result[$keyToPlace], $resultAdditional[$keyToPlace], $required[$key]] = $this->processFormRequestRules($row, $rulesRaw, [], false, $key, $fullPath);
                // Mark as required self
                if (is_array($rulesRawThis = $rulesRaw[$fullPath] ?? null)) {
                    $required[$key]['__self'] = in_array('required', $rulesRawThis, true);
                }
                continue;
            }
            $required[$key] = in_array('required', $row, true);
            foreach ($row as $ruleRow) {
                [$ruleName] = $this->normalizeFormRequestValidationRule($ruleRow);
                $keyForExample = $key === '*' && $parent !== null ? $parent : $key;
                if (($example = $this->describer()->example(null, null, $keyForExample, $ruleName)) !== null) {
                    $additionalParams = $this->parseAdditionalParamsFromRequestValidationRules($row, $example);
                    if ($key === '*') {
                        $result = [$example];
                        $resultAdditional = [$additionalParams];
                    } else {
                        $result[$key] = $example;
                        $resultAdditional[$key] = array_merge($resultAdditional[$key] ?? [], $additionalParams);
                    }
                    break;
                }
            }
        }
        // Describe optionally
        if ($describe) {
            $result = $this->describer()->describe($result, $resultAdditional);
            $this->applyFromRequestLabelsToRules($result, $labels);
        }

        return [$result, $resultAdditional, $required];
    }

    /**
     * Get additional params for the variable description by all validation rules.
     *
     * @param  array $rules
     * @param  mixed $value
     * @return array
     */
    protected function parseAdditionalParamsFromRequestValidationRules(array $rules, $value): array
    {
        $params = [];
        foreach ($rules as $ruleRow) {
            [$ruleName, $ruleParams] = $this->normalizeFormRequestValidationRule($ruleRow);
            if (! is_string($ruleName)) {
                continue;
            }
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $params = array_merge($params, $this->getAdditionalParamsFromRequestValidationRule($ruleName, $ruleParams, $value));
        }

        return $params;
    }

    /**
     * Get additional parameters for value description by its validation rules.
     *
     * @param  string $ruleName
     * @param  array  $ruleParams
     * @param  mixed  $value
     * @return array
     */
    protected function getAdditionalParamsFromRequestValidationRule(string $ruleName, array $ruleParams, $value): array
    {
        $params = [];
        switch ($ruleName) {
            case 'min':
                if (($min = reset($ruleParams)) !== false) {
                    $paramKey = is_numeric($value) ? 'minimum' : 'minLength';
                    $paramKey = is_array($value) ? 'minItems' : $paramKey;
                    $params[$paramKey] = str_contains((string) $min, '.') ? (float)$min : (int)$min;
                }
                break;
            case 'max':
                if (($max = reset($ruleParams)) !== false) {
                    $paramKey = is_numeric($value) ? 'maximum' : 'maxLength';
                    $paramKey = is_array($value) ? 'maxItems' : $paramKey;
                    $params[$paramKey] = str_contains((string) $max, '.') ? (float)$max : (int)$max;
                }
                break;
            case 'between':
                if (count($ruleParams) >= 2) {
                    [$min, $max] = $ruleParams;
                    $paramsLocal = [
                        $this->getAdditionalParamsFromRequestValidationRule('min', [$min], $value),
                        $this->getAdditionalParamsFromRequestValidationRule('max', [$max], $value),
                    ];
                    $params = array_merge($params, ...$paramsLocal);
                }
                break;
            case 'in':
                $ruleParams = array_filter($ruleParams, fn ($v) => (string)$v !== "");
                if (! empty($ruleParams)) {
                    sort($ruleParams);
                    $params['enum'] = $ruleParams;
                }
                break;
            case 'nullable':
                $params['nullable'] = true;
                break;
        }

        return $params;
    }

    /**
     * Normalize validation rule data for the request.
     *
     * @param  string|mixed $rule
     * @return array
     */
    protected function normalizeFormRequestValidationRule(mixed $rule): array
    {
        if (! is_string($rule) || ! str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $ruleParamsStr] = explode(':', $rule);
        // Trim whitespace and possible quotes
        $ruleParams = array_map(function ($v) {
            $v = trim($v);

            return ($trimmed = trim($v, '"\'')) !== "" ? $trimmed : $v;
        }, explode(',', $ruleParamsStr));

        return [$ruleName, $ruleParams];
    }

    /**
     * Apply labels as description to described validation rules.
     *
     * @param  array $rules
     * @param  array $labels
     */
    protected function applyFromRequestLabelsToRules(array &$rules, array $labels): void
    {
        if (empty($labels) || empty($rules['properties'])) {
            return;
        }

        foreach ($labels as $attribute => $label) {
            $attributeNormalized = $attribute;
            // Attribute names with a wildcard (*)
            if (str_contains((string) $attribute, '*')) {
                $attributeNormalized = str_replace('*', 'items', str_replace('*.', 'items.properties.', $attribute));
                if (Arr::get($rules['properties'], $attributeNormalized) !== null) {
                    Arr::set($rules['properties'], $attributeNormalized . '.description', Str::ucfirst($label));
                }
                // Regular attribute names
            } elseif (isset($rules['properties'][$attribute])) {
                $rules['properties'][$attribute]['description'] = Str::ucfirst($label);
            }
        }
    }

    /**
     * Get form request annotations (\OA\RequestBody)
     *
     * @param  string $className
     * @return array
     */
    protected function parseFormRequestAnnotations(string $className): array
    {
        $annotations = $this->classAnnotations($className, \OA\RequestBody::class);
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
     *
     * @param  Route $route
     * @return bool
     */
    protected function isRouteIgnored(Route $route): bool
    {
        return $this->routeAnnotation($route, \OA\Ignore::class) !== null
            || ! empty($this->controllerAnnotations($route, \OA\Ignore::class));
    }

    /**
     * Check whether class or its method has an `OA\Ignore` annotation.
     *
     * @param  string      $className
     * @param  string|null $method
     * @return bool
     */
    protected function hasIgnoreAnnotation(string $className, ?string $method = null): bool
    {
        return $method !== null
            ? $this->methodAnnotation([$className, $method], \OA\Ignore::class) !== null
            : $this->classAnnotation($className, \OA\Ignore::class) !== null;
    }

    /**
     * Check that route must be processed
     *
     * @param  Route      $route
     * @param  array      $matches
     * @param  array      $matchesNot
     * @param  array $only
     * @param  array $except
     * @return bool
     */
    protected function checkRoute(Route $route, array $matches = [], array $matchesNot = [], array $only = [], array $except = []): bool
    {
        if ($this->isRouteIgnored($route)) {
            return false;
        }
        $uri = '/' . ltrim($route->uri, '/');
        if (! empty($except) && in_array($uri, $except, true)) {
            return false;
        }
        if (! empty($only) && in_array($uri, $only, true)) {
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
     *
     * @param  \Illuminate\Routing\Route $route
     * @return string[]
     */
    protected function getRouteTags(Route $route): array
    {
        $methodRef = $this->routeReflection($route);
        $tags = [];
        if ($methodRef instanceof \ReflectionMethod) {
            $controllerNames = [
                is_object($route->getController()) ? get_class($route->getController()) : null, // Class from route definition
                $methodRef->class, // Class from reflection, where real method written
            ];
            $controllerNames = array_unique(array_filter($controllerNames));
            $annotationsClass = [];
            $annotationsMethod = [];
            foreach ($controllerNames as $controllerName) {
                // Get annotation only if previously not found any
                $annotationsClass = empty($annotationsClass) ? $this->classAnnotations($controllerName, \OA\Tag::class) : $annotationsClass;
                $annotationsMethod = empty($annotationsMethod) ? $this->routeAnnotations($route, \OA\Tag::class) : $annotationsMethod;
            }
            $annotations = $this->describer()->merge($annotationsClass, $annotationsMethod);
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
     * Get route security definitions.
     *
     * @param  \Illuminate\Routing\Route $route
     * @return array|null
     */
    protected function getRouteSecurity(Route $route): ?array
    {
        $methodRef = $this->routeReflection($route);
        $results = [];
        if ($methodRef instanceof \ReflectionMethod) {
            /** @var \OA\Secured[] $annotations */
            $annotations = $this->routeAnnotations($route, \OA\Secured::class);
            foreach ($annotations as $annotation) {
                $results[] = $annotation->toArray();
            }
        }

        return ! empty($results) ? $results : null;
    }

    /**
     * Get route summary
     *
     * @param  \Illuminate\Routing\Route $route
     * @return string|null
     */
    protected function getRouteSummary(Route $route): ?string
    {
        $methodRef = $this->routeReflection($route);
        if (($docComment = $methodRef->getDocComment()) !== false) {
            return $this->getDocFactory()->create($docComment)->getSummary();
        }

        return null;
    }

    /**
     * Get description of the route.
     *
     * @param  \Illuminate\Routing\Route $route
     * @return string|null
     */
    protected function getRouteDescription(Route $route): ?string
    {
        $methodRef = $this->routeReflection($route);
        $description = '';
        if (($docComment = $methodRef->getDocComment()) !== false) {
            $docblock = $this->getDocFactory()->create($docComment);
            $description = $docblock->getDescription()->__toString();
        }
        // Parse description extenders
        $annotationsMethod = $this->methodAnnotations($methodRef, \OA\DescriptionExtender::class);
        $annotationsClass = $this->controllerAnnotations($route, \OA\DescriptionExtender::class, true, false);
        /** @var \OA\DescriptionExtender[] $annotations */
        $annotations = $this->describer()->merge($annotationsClass, $annotationsMethod);
        foreach ($annotations as $annotation) {
            if (($descriptionAnn = $annotation->setAction($route->getActionMethod())->__toString()) === '') {
                continue;
            }
            $description .= "\n\n" . $descriptionAnn;
        }
        return $description !== '' ? $description : null;
    }

    /**
     * Get route param type by matching to regex
     *
     * @param  \Illuminate\Routing\Route $route
     * @param  string                    $paramName
     * @param  string                    $default
     * @return string
     */
    protected function getRouteParamType(Route $route, string $paramName, string $default = 'integer'): string
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

        return count($types) === 1 ? reset($types) : $default;
    }
}
