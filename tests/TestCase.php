<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Tonsoo\FilamentTranslatable\FilamentTranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentTranslatableServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('app.available_locales', ['en', 'es']);
    }
}
