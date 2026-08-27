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
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || ! preg_match('/_test\z/i', $database)) {
            throw new RuntimeException(
                "Safety stop: automated tests may only run in APP_ENV=testing on a database ending in _test; resolved {$connection}/{$database}.",
            );
        }

        return $app;
    }
}
