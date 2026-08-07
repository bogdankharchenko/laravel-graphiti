<?php

namespace BogdanKharchenko\Graphiti;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class GraphitiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/graphiti.php', 'graphiti');

        $this->app->singleton(GraphitiClient::class, function (Application $app) {
            $config = $app->make('config')->get('graphiti');

            return new GraphitiClient(
                http: $app->make(Factory::class),
                baseUrl: $config['url'],
                timeout: $config['timeout'],
                retryTimes: $config['retry']['times'],
                retrySleepMs: $config['retry']['sleep_ms'],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/graphiti.php' => config_path('graphiti.php'),
            ], 'graphiti-config');
        }
    }
}
