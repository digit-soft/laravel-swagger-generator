<?php

namespace DigitSoft\Swagger\Commands;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\RoutesParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

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
        $this->getOutput()->success("Command {$this->getName()} run!");
        $dumper = $this->getDumper();
        $filePath = $this->getMainFile();
        $arrayContent = config('swagger-generator.content', []);
        $arrayContent['paths'] = $this->getRoutesData();
        $content = $dumper->toYml($arrayContent);
        $this->files->put($filePath, $content);
        //$this->getOutput()->comment($filePath);
    }

    protected function getRoutesData()
    {
        $parser = new RoutesParser($this->routes);
        return $parser->parse();
    }

    /**
     * Get path to main yml file
     * @return string
     */
    protected function getMainFile()
    {
        $path = config('swagger-generator.output.path');
        $path = app()->basePath($path);
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
