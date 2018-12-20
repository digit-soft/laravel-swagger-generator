<?php

namespace DigitSoft\Swagger\Commands;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\RoutesParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    protected $name = 'swagger:generate';

    protected $description = 'Generate Swagger documentation';
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
     * @throws \Exception
     */
    public function handle()
    {
        $dumper = $this->getDumper();
        $filePath = $this->getMainFile();
        $arrayContent = config('swagger-generator.content', []);
        $arrayContent = $this->mergeWithFilesContent($arrayContent, config('swagger-generator.contentFilesBefore', []));
        $routesData = $this->getRoutesData();
        $arrayContent = DumperYaml::merge($arrayContent, $routesData);
        $arrayContent = $this->mergeWithFilesContent($arrayContent, config('swagger-generator.contentFilesAfter', []));
        $content = $dumper->toYml($arrayContent);
        $this->files->put($filePath, $content);
        $this->getOutput()->success(strtr("Swagger YML file generated to {file}", ['{file}' => $filePath]));
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
        $dumper = $this->getDumper();
        foreach ($fileList as $fileName) {
            if ($this->files->exists($fileName) && ($row = $dumper->fromYml($fileName)) !== null) {
                $filesContent[] = $row;
            }
        }
        if (empty($filesContent)) {
            return $data;
        }
        return DumperYaml::merge($data, ...$filesContent);
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
            $paths = DumperYaml::merge($paths, $routes);
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

    /**
     * Get yml dumper
     * @return DumperYaml
     */
    protected function getDumper()
    {
        return app()->make(DumperYaml::class);
    }
}
