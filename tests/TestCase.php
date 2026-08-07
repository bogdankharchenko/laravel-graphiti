<?php

namespace BogdanKharchenko\Graphiti\Tests;

use BogdanKharchenko\Graphiti\GraphitiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GraphitiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('graphiti.url', 'http://graphiti.test');
        $app['config']->set('graphiti.retry.sleep_ms', 0);
    }
}
