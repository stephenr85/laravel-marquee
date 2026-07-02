<?php

declare(strict_types=1);

namespace Rushing\Marquee\Tests;

use Illuminate\Routing\Router;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Marquee\MarqueeServiceProvider;
use Rushing\Marquee\Middleware\EnforceSiteMode;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set AFTER the provider's mergeConfigFrom runs — a config override in
        // defineEnvironment would be recursively re-merged with (and thus keep)
        // the package default ['local','testing'], never actually clearing it.
        // With the list emptied here, the gate fires under the test env; the
        // hard-live default is exercised in its own dedicated test.
        config()->set('marquee.live_environments', []);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = [MarqueeServiceProvider::class];

        // Present as a dev dependency; lets the maintenance-banner test assert
        // the shared `marquee_mode` prop without forcing Inertia on consumers.
        if (class_exists(ServiceProvider::class)) {
            $providers[] = ServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = require __DIR__.'/../database/migrations/create_marquee_state_table.php.stub';
        $migration->up();
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->middleware(EnforceSiteMode::class)->group(function (Router $router) {
            $router->get('app-home', fn () => '<div id="app">REAL APP HOME</div>');
            $router->get('health', fn () => 'HEALTH OK');
            $router->get('stripe/webhook', fn () => 'STRIPE OK');
            $router->get('webhooks/incoming', fn () => 'WEBHOOK OK');
            $router->get('custom-open', fn () => 'CUSTOM OPEN');
        });
    }
}
