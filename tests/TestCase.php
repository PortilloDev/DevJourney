<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // The docker-compose container exports DB_CONNECTION, QUEUE_CONNECTION,
        // etc. into $_SERVER, which takes precedence over phpunit.xml's <env>
        // values when Laravel reads them. Mirror the phpunit-forced values into
        // $_SERVER so the suite stays hermetic: isolated in-memory SQLite, sync
        // queue and in-memory cache/session — never the dev MySQL/Redis.
        if (getenv('APP_ENV') === 'testing') {
            $keys = [
                'APP_ENV',
                'DB_CONNECTION',
                'DB_DATABASE',
                'QUEUE_CONNECTION',
                'CACHE_STORE',
                'SESSION_DRIVER',
                'MAIL_MAILER',
            ];

            foreach ($keys as $key) {
                $value = getenv($key);
                $_SERVER[$key] = $value === false ? null : $value;
            }

            $_SERVER['DB_HOST'] = null;
        }

        parent::setUp();
    }
}
