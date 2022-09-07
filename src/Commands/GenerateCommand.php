<?php

namespace DigitSoft\Swagger\Commands;

use Illuminate\Support\Arr;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Console\Command;
use DigitSoft\Swagger\RoutesParser;
use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use DigitSoft\Swagger\Parser\WithDocParser;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Parser\WithVariableDescriber;

/**
 * Main command to generate documentation.
 */
class GenerateCommand extends Command
{
    use WithVariableDescriber, WithReflections, WithDocParser;

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

    /**
     * CreateMigrationCommand constructor.
     *
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
     * Handle command.
     *
     * @return int
     */
    public function handle(): int
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
        $definitions = $this->generateAdditionalDefinitions();
        $arrayContent = $this->describer()->merge($arrayContent, $definitions);
        $content = $this->describer()->toYml($arrayContent);
        $this->files->put($filePath, $content);
        $this->getOutput()->success(sprintf("Swagger YML file generated to '%s'", $filePath));
        $this->printTimeSpent($startTime);

        return 0;
    }

    /**
     * Handle request in diagnose mode.
     *
     * @return int
     */
    protected function handleDiagnose(): int
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

        if (! $this->getOutput()->isVerbose()) {
            $this->getOutput()->title('To see additional information use option -v.');
        }

        $this->printTimeSpent($startTime);

        return 0;
    }

    /**
     * Print time spent from given point.
     *
     * @param  string $startTime
     */
    protected function printTimeSpent(string $startTime): void
    {
        $start = \DateTime::createFromFormat('0.u00 U', $startTime);
        $finish = \DateTime::createFromFormat('0.u00 U', microtime());
        $diff = $start->diff($finish);
        $this->getOutput()->title('Time spent: ' . $diff->format('%H:%I:%S.%F'));
    }

    /**
     * Get problem label.
     *
     * @param  string $key
     * @return string
     */
    protected function getProblemLabel(string $key): string
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
     * Check that diagnose mode enabled.
     *
     * @return bool
     */
    protected function isDiag(): bool
    {
        return $this->option('diagnose');
    }

    /**
     * Merge data with content of YML files.
     *
     * @param  array $data
     * @param  array $fileList
     * @return array
     */
    protected function mergeWithFilesContent(array $data = [], array $fileList = []): array
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
     * Get data from routes parser.
     *
     * @return array
     */
    protected function getRoutesData(): array
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
            array_walk($responses, function (&$value) { $value = [$value]; });
            array_walk($requests, function (&$value) { $value = [$value]; });
            $this->getOutput()->table(['Responses'], $responses);
            $this->getOutput()->table(['Request Bodies'], $requests);
        }

        return $data;
    }

    /**
     * Generate additional definitions.
     *
     * @return array
     */
    protected function generateAdditionalDefinitions(): array
    {
        $classes = config('swagger-generator.generateDefinitions', []);
        $definitions = [];
        foreach ($classes as $item) {
            [$className, $classBaseName, $classDescription, $classWith] = $this->normalizeModelDefinitionConfigItem($item);
            $classBaseName = $classBaseName ?? class_basename($className);
            if ($classDescription === null) {
                $docStr = $this->docBlockClass($className);
                $classDescription = is_string($docStr) ? $this->getDocSummary($docStr) : null;
            }
            $variable = Variable::fromDescription([
                'type' => $className,
                'with' => $classWith,
                'description' => $classDescription,
            ]);
            $classDefinition = $variable->describe();
            $definitions[$classBaseName] = $classDefinition;
        }

        return ! empty($definitions) ? ['components' => ['schemas' => $definitions]] : [];
    }

    /**
     * Generate additional definitions.
     *
     * @param  string|array $itemRaw
     * @return array
     */
    private function normalizeModelDefinitionConfigItem(array|string $itemRaw): array
    {
        $item = ['', null, null, []];

        if (is_string($itemRaw)) {
            $item[0] = $itemRaw;
        } elseif (is_array($itemRaw)) {
            if (isset($itemRaw[3])) {
                $itemRaw[3] = Arr::wrap($itemRaw[3]);
            }
            $item = $itemRaw + $item;
        }

        return $item;
    }

    /**
     * Sort router paths.
     *
     * @param  array $paths
     */
    protected function sortPaths(array &$paths)
    {
        // ksort($paths);
        $byTags = [];
        // Group by first tag
        foreach ($paths as $path => $route) {
            // Sort by method in same path
            uksort($route, function ($a, $b) {
                $methods = ['head', 'get', 'post', 'patch', 'put', 'delete'];
                $aPos = array_search($a, $methods, true);
                $bPos = array_search($b, $methods, true);

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
        foreach ($byTags as $routes) {
            $paths = $this->describer()->merge($paths, $routes);
        }
    }

    /**
     * Get path to main yml file.
     *
     * @return string
     */
    protected function getMainFile()
    {
        $path = config('swagger-generator.output.path');
        // absolute path
        if (! str_starts_with($path, '/')) {
            $path = app()->basePath($path);
        }
        if (! $this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        return $path . DIRECTORY_SEPARATOR . config('swagger-generator.output.file_name');
    }
}
