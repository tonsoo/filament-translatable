<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable;

use Illuminate\Support\ServiceProvider;
use Tonsoo\FilamentTranslatable\Support\FieldRelationshipTranslatableDataConfigurator;

class FilamentTranslatableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-translatable');

        $configurator = app(FieldRelationshipTranslatableDataConfigurator::class);
        $configurator->configure();
    }
}
