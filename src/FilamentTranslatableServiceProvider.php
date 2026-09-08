<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable;

use Filament\Schemas\Components\Component;
use Illuminate\Support\ServiceProvider;
use Tonsoo\FilamentTranslatable\Support\FieldRelationshipTranslatableDataConfigurator;
use Tonsoo\FilamentTranslatable\Support\TranslatableComponents;

class FilamentTranslatableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-translatable');

        $configurator = app(FieldRelationshipTranslatableDataConfigurator::class);
        $configurator->configure();

        $this->registerComponentMacros();
    }

    protected function registerComponentMacros(): void
    {
        if (! class_exists(Component::class)) {
            return;
        }

        if (! Component::hasMacro('translatable')) {
            Component::macro('translatable', function (bool $condition = true): static {
                TranslatableComponents::mark($this, $condition);

                return $this;
            });
        }

        if (! Component::hasMacro('isTranslatable')) {
            Component::macro('isTranslatable', function (): bool {
                return TranslatableComponents::isMarked($this);
            });
        }
    }
}
