<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Illuminate\Console\OutputStyle;

/**
 * Trait RoutesParserEvents
 */
trait RoutesParserEvents
{
    /**
     * @var Route[]
     */
    protected array $skippedRoutes = [];
    /**
     * @var array
     */
    protected array $failedFormRequests = [];

    /**
     * Trigger event
     *
     * @param  string $event
     * @param  mixed  ...$params
     * @return bool|mixed
     */
    protected function trigger(string $event, ...$params): mixed
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
    protected function eventParseStart(): void
    {
        if (! $this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressStart(count($this->routes));
    }

    /**
     * Finish parsing
     */
    protected function eventParseFinish(): void
    {
        if (! $this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressFinish();
        if (! empty($this->skippedRoutes)) {
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
        if (! empty($this->failedFormRequests)) {
            $failedRequests = [];
            foreach ($this->failedFormRequests as $key => $row) {
                /** @var \Throwable $exception */
                [$className, $exception] = $row;
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
     *
     * @param  Route $route
     */
    protected function eventRouteProcessed(Route $route): void
    {
        if (! $this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }

    /**
     * Route skipped
     *
     * @param  Route $route
     */
    protected function eventRouteSkipped(Route $route): void
    {
        $this->skippedRoutes[] = $route;
        if (! $this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }

    /**
     * Failed to fetch from request data
     *
     * @param  \Illuminate\Foundation\Http\FormRequest $request
     * @param  string|null                             $exception
     */
    protected function eventRouteFormRequestFailed(object $request, ?string $exception = null): void
    {
        $this->failedFormRequests[] = [get_class($request), $exception];
    }

    /**
     * Some problem found
     *
     * @param  string      $problemType
     * @param  Route       $route
     * @param  string|null $additional
     */
    protected function eventProblemFound(string $problemType, Route $route, ?string $additional = null): void
    {
        $this->problems[$problemType] = $this->problems[$problemType] ?? [];
        $this->problems[$problemType][$route->uri()] = [$route, $additional];
    }
}
