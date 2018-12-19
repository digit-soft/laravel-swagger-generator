<?php

namespace DigitSoft\Swagger\Commands;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\RoutesParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
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
        $routesData = $this->getRoutesData();
        $arrayContent = DumperYaml::merge($arrayContent, $routesData);
        $content = $dumper->toYml($arrayContent);
        $this->files->put($filePath, $content);
        $this->getOutput()->success(strtr("Swagger YML file generated to {file}", ['{file}' => $filePath]));
    }

    /**
     * Get data from routes parser
     * @return array
     */
    protected function getRoutesData()
    {
        $parser = new RoutesParser($this->routes, $this->getOutput());
        $paths = $parser->parse();
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
