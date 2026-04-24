<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Tonsoo\FilamentTranslatable\Resources\Concerns\HasTranslatableLocaleSwitcher;

it('reads static translatable locales without requiring labels', function (): void {
    expect(FakeLocaleSwitcherResource::getTranslatableLocales())->toBe(['en', 'es']);
});

it('reads the default translatable locale without requiring labels', function (): void {
    expect(FakeLocaleSwitcherResource::getDefaultTranslatableLocale())->toBe('es');
});

class FakeLocaleSwitcherResource
{
    use HasTranslatableLocaleSwitcher;

    protected static array $translatableLocales = ['en', 'es'];

    protected static string $defaultTranslatableLocale = 'es';
}
