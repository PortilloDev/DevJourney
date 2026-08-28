<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // The docker-compose container exports DB_CONNECTION/DB_DATABASE into
        // $_SERVER, which takes precedence over phpunit.xml's <env> values when
        // Laravel reads them. Force the test suite onto an isolated in-memory
        // SQLite database so tests never touch (or wipe) the dev MySQL data.
        if (getenv('APP_ENV') === 'testing') {
            $_SERVER['DB_CONNECTION'] = getenv('DB_CONNECTION');
            $_SERVER['DB_DATABASE'] = getenv('DB_DATABASE');
            $_SERVER['DB_HOST'] = null;
        }

        parent::setUp();
    }
}
