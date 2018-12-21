<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Trait RoutesParserEvents
 * @package DigitSoft\Swagger\Parser
 */
trait RoutesParserEvents
{
    /**
     * @var Route[]
     */
    protected $skippedRoutes = [];
    /**
     * @var array
     */
    protected $failedFormRequests = [];

    /**
     * Trigger event
     * @param string $event
     * @param mixed  ...$params
     * @return bool|mixed
     */
    protected function trigger($event, ...$params)
    {
        $methodName = 'event' . ucfirst(Str::camel($event));
        if (method_exists($this, $methodName)) {
            return call_user_func_array([$this, $methodName], $params);
        }
        return false;
    }

    /**
     * Start parsing
     */
    protected function eventParseStart()
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressStart(count($this->routes));
    }

    /**
     * Finish parsing
     */
    protected function eventParseFinish()
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressFinish();
        if (!empty($this->skippedRoutes)) {
            $this->output->warning(strtr('There are {count} skipped routes', ['{count}' => count($this->skippedRoutes)]));
            if ($this->output->isVerbose()) {
                $routePaths = [];
                foreach ($this->skippedRoutes as $route) {
                    $routePaths[] = [
                        $route->uri(),
                        json_encode($route->methods()),
                    ];
                }
                $this->output->table(['URI', 'Methods'], $routePaths);
            }
        }
        if (!empty($this->failedFormRequests)) {
            $failedRequests = [];
            foreach ($this->failedFormRequests as $key => $row) {
                /** @var \Throwable $exception */
                $className = $row[0];
                $exception = $row[1];
                $exceptionStr = $exception ? get_class($exception) . "\n" . $exception->getMessage() : '-';
                $failedRequests[$className] = [$className, $exceptionStr];
            }
            $this->output->warning(strtr('There are {count} form requests where failed to get rules', ['{count}' => count($failedRequests)]));
            if ($this->output->isVerbose()) {
                $this->output->table(['Class name', 'Exception'], $failedRequests);
            }
        }
    }

    /**
     * Route processed
     * @param Route $route
     */
    protected function eventRouteProcessed($route)
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }

    /**
     * Route skipped
     * @param Route $route
     */
    protected function eventRouteSkipped(Route $route)
    {
        $this->skippedRoutes[] = $route;
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }

    /**
     * Failed to fetch from request data
     * @param FormRequest $request
     * @param null $exception
     */
    protected function eventRouteFormRequestFailed($request, $exception = null)
    {
        $this->failedFormRequests[] = [get_class($request), $exception];
    }

    /**
     * Some problem found
     * @param string $problemType
     * @param Route $route
     * @param string|null  $additional
     */
    protected function eventProblemFound($problemType, Route $route, $additional = null)
    {
        $this->problems[$problemType] = $this->problems[$problemType] ?? [];
        $this->problems[$problemType][$route->uri()] = [$route, $additional];
    }
}
