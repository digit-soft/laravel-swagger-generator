<?php

namespace DigitSoft\Swagger\Parser;

use Illuminate\Console\OutputStyle;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
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

    protected function eventParseStart()
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressStart(count($this->routes));
    }

    protected function eventParseFinish()
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressFinish();
        if (!empty($this->skippedRoutes)) {
            $routePaths = [];
            foreach ($this->skippedRoutes as $route) {
                $routePaths[] = [
                    $route->uri(),
                    json_encode($route->methods()),
                ];
            }
            $this->output->warning(strtr('There are {count} skipped routes', ['{count}' => count($this->skippedRoutes)]));
            $this->output->table(['URI', 'Methods'], $routePaths);
        }
    }

    protected function eventRouteProcessed($route)
    {
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }

    protected function eventRouteSkipped(Route $route)
    {
        $this->skippedRoutes[] = $route;
        if (!$this->output instanceof OutputStyle) {
            return;
        }
        $this->output->progressAdvance();
    }
}
