<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__) . '/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->detectEnvironment(static fn (): string => 'testing');

        $config = $app->make('config');
        $config->set('app.env', 'testing');
        $config->set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        $config->set('database.default', 'sqlite');
        $config->set('deltasupport.legacy_financial_actions', true);

        $sqlite = $config->get('database.connections.sqlite');
        $sqlite['database'] = ':memory:';
        $sqlite['url'] = null;

        foreach (array_keys($config->get('database.connections', [])) as $connection) {
            $app->make('db')->purge($connection);
            $config->set('database.connections.' . $connection, $sqlite);
        }

        return $app;
    }
}
