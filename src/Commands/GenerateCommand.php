<?php

namespace DigitSoft\Swagger\Commands;

use DigitSoft\Swagger\Parser\WithVariableDescriber;
use DigitSoft\Swagger\RoutesParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;

class GenerateCommand extends Command
{
    public $diagnose = false;

    protected $name = 'swagger:generate';

    protected $description = 'Generate Swagger documentation';

    protected $signature = 'swagger:generate {--diagnose}';

    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var Router
     */
    protected $router;
    /**
     * @var Route[]|RouteCollection
     */
    protected $routes;

    use WithVariableDescriber;

    /**
     * CreateMigrationCommand constructor.
     * @param  Router     $router
     * @param  Filesystem $files
     */
    public function __construct(Router $router, Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
        $this->router = $router;
        $this->routes = $router->getRoutes();
    }

    /**
     * Handle command
     */
    public function handle()
    {
        if ($this->isDiag()) {
            return $this->handleDiagnose();
        }
        $startTime = microtime();
        $filePath = $this->getMainFile();
        $arrayContent = config('swagger-generator.content', []);
        $arrayContent = $this->mergeWithFilesContent($arrayContent, config('swagger-generator.contentFilesBefore', []));
        $routesData = $this->getRoutesData();
        $arrayContent = $this->describer()->merge($arrayContent, $routesData);
        $arrayContent = $this->mergeWithFilesContent($arrayContent, config('swagger-generator.contentFilesAfter', []));
        $content = $this->describer()->toYml($arrayContent);
        $this->files->put($filePath, $content);
        $this->getOutput()->success(strtr("Swagger YML file generated to {file}", ['{file}' => $filePath]));
        $this->printTimeSpent($startTime);
    }

    /**
     * Handle request in diagnose mode
     */
    protected function handleDiagnose()
    {
        $startTime = microtime();
        $this->getOutput()->success('Diagnose mode, files will not be generated.');
        $parser = new RoutesParser($this->routes, $this->getOutput());
        $paths = $parser->parse();
        $routesCount = 0;
        array_walk($paths, function ($value) use (&$routesCount) { $routesCount += count($value); });
        $this->getOutput()->success(strtr('There are {count} route(s) parsed.', ['{count}' => $routesCount]));

        foreach ($parser->problems as $key => $routes) {
            $label = $this->getProblemLabel($key);
            $label .= ' (' . count($routes) . ' occurrence(s))';
            $this->getOutput()->warning($label);
            if ($this->getOutput()->isVerbose()) {
                $table = [];
                foreach ($routes as $routeData) {
                    /** @var Route $route */
                    $route = $routeData[0];
                    $additional = $routeData[1] ?? null;
                    $table[] = [
                        $route->uri(),
                        $route->getActionName(),
                        $additional,
                    ];
                }
                $this->getOutput()->table(['URI', 'Controller', 'Additional'], $table);
            }
        }
        if (!$this->getOutput()->isVerbose()) {
            $this->getOutput()->title('To see additional information use option -v.');
        }
        $this->printTimeSpent($startTime);
    }

    /**
     * Print time spent from given point
     * @param string $startTime
     */
    protected function printTimeSpent($startTime)
    {
        $finishTime = microtime();
        $start = \DateTime::createFromFormat('0.u00 U', $startTime);
        $finish = \DateTime::createFromFormat('0.u00 U', $finishTime);
        $diff = $finish->diff($start);
        $this->getOutput()->title('Time spent: ' . $diff->format('%H:%I:%S.%F'));
    }

    /**
     * Get problem label
     * @param string $key
     * @return mixed|string
     */
    protected function getProblemLabel($key)
    {
        $labels = [
            RoutesParser::PROBLEM_NO_RESPONSE => 'Route has no described response body',
            RoutesParser::PROBLEM_ROUTE_CLOSURE => 'Route is handled by closure. Closure not supports annotations.',
            RoutesParser::PROBLEM_NO_DOC_CLASS => 'There is not PHPDoc for class.',
            RoutesParser::PROBLEM_MISSING_TAG => 'Route "Tags" are not set.',
            RoutesParser::PROBLEM_MISSING_PARAM => 'Route "Parameter" not described.',
        ];
        return $labels[$key] ?? ucfirst(str_replace(['-', '_'], ' ', $key));
    }

    /**
     * Check that diagnose mode enabled
     * @return bool
     */
    protected function isDiag()
    {
        return $this->option('diagnose');
    }

    /**
     * Merge data with content of YML files
     * @param array $data
     * @param array $fileList
     * @return array
     */
    protected function mergeWithFilesContent($data = [], $fileList = [])
    {
        if (empty($fileList)) {
            return $data;
        }
        $filesContent = [];
        foreach ($fileList as $fileName) {
            if ($this->files->exists($fileName) && ($row = $this->describer()->fromYml($fileName)) !== null) {
                $filesContent[] = $row;
            }
        }
        if (empty($filesContent)) {
            return $data;
        }
        return $this->describer()->merge($data, ...$filesContent);
    }

    /**
     * Get data from routes parser
     * @return array
     */
    protected function getRoutesData()
    {
        $parser = new RoutesParser($this->routes, $this->getOutput());
        $paths = $parser->parse();
        $this->sortPaths($paths);
        ksort($parser->components['responses']);
        ksort($parser->components['requestBodies']);
        $data = ['paths' => $paths, 'components' => $parser->components];
        if ($this->getOutput()->isVerbose()) {
            $responses = array_keys($parser->components['responses']);
            $requests = array_keys($parser->components['requestBodies']);
            array_walk($responses, function (&$value){ $value = [$value]; });
            array_walk($requests, function (&$value){ $value = [$value]; });
            $this->getOutput()->table(['Responses'], $responses);
            $this->getOutput()->table(['Request Bodies'], $requests);
        }
        return $data;
    }

    /**
     * Sort router paths
     * @param array $paths
     */
    protected function sortPaths(&$paths)
    {
        ksort($paths);
        $byTags = [];
        // Group by first tag
        foreach ($paths as $path => $route) {
            // Sort by method in same path
            uksort($route, function ($a, $b) {
                $methods = ['head', 'get', 'post', 'patch', 'put', 'delete'];
                $aPos = array_search($a, $methods);
                $bPos = array_search($b, $methods);
                return $aPos < $bPos ? -1 : 1;
            });
            $firstMethod = reset($route);
            $tag = reset($firstMethod['tags']);
            $byTags[$tag][$path] = $route;
        }
        // Sort tags
        ksort($byTags);
        // Rewrite paths array
        $paths = [];
        foreach ($byTags as $tag => $routes) {
            $paths = $this->describer()->merge($paths, $routes);
        }
    }

    /**
     * Get path to main yml file
     * @return string
     */
    protected function getMainFile()
    {
        $path = config('swagger-generator.output.path');
        // absolute path
        if (strpos($path, '/') !== 0) {
            $path = app()->basePath($path);
        }
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
        return $path . DIRECTORY_SEPARATOR . config('swagger-generator.output.file_name');
    }
}
