<?php

namespace DigitSoft\Swagger;

use Illuminate\Support\ServiceProvider;
use DigitSoft\Swagger\Commands\GenerateCommand;

class SwaggerGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events and publish config.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/swagger-generator.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('swagger-generator.php');
        } else {
            $publishPath = base_path('config/swagger-generator.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/swagger-generator.php';
        $this->mergeConfigFrom($configPath, 'swagger-generator');
        $this->registerCommands();
        $this->registerComponents();
    }

    /**
     * Register package components
     */
    protected function registerComponents()
    {
        $this->app->singleton('swagger.describer', function ($app) {
            return new VariableDescriberService($app['files']);
        });
        $this->app->alias('swagger-describer', VariableDescriberService::class);
    }

    /**
     * Register console commands
     */
    protected function registerCommands()
    {
        $this->app->singleton('command.swagger.generate', function ($app) {
            return new GenerateCommand($app['router'], $app['files']);
        });

        $this->commands([
            'command.swagger.generate',
        ]);
    }
}
