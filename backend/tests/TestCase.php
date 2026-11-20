<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        if (! $app->environment('testing') || $connection->getDriverName() !== 'pgsql'
            || ! preg_match('/_test(?:_\d+)?$/', $connection->getDatabaseName())) {
            throw new RuntimeException('Use a dedicated PostgreSQL database with a name ending in _test.');
        }

        return $app;
    }
}
