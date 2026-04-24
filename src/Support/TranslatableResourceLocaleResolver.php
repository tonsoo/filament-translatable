<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

final class TranslatableResourceLocaleResolver
{
    /**
     * @return array<int, string>
     */
    public function resolveLocales(string $resource): array
    {
        if (method_exists($resource, 'getTranslatableLocales')) {
            $locales = array_values(array_unique(array_filter((array) $resource::getTranslatableLocales())));
            if ($locales !== []) {
                return $locales;
            }
        }

        if (property_exists($resource, 'translatableLocales')) {
            $locales = array_values(array_unique(array_filter((array) $resource::$translatableLocales)));
            if ($locales !== []) {
                return $locales;
            }
        }

        $availableLocales = config('app.available_locales');
        if (! is_array($availableLocales) || $availableLocales === []) {
            $availableLocales = config('app.locales');
        }

        $locales = array_values(array_unique(array_filter((array) $availableLocales)));
        if ($locales !== []) {
            return $locales;
        }

        $appLocale = (string) app()->getLocale();

        return $appLocale !== '' ? [$appLocale] : [];
    }

    public function hasLocaleSwitcher(string $resource): bool
    {
        return method_exists($resource, 'hasTranslatableLocaleSwitcher')
            ? (bool) $resource::hasTranslatableLocaleSwitcher()
            : false;
    }

    /**
     * @return array<string, string>
     */
    public function resolveSwitcherOptions(string $resource): array
    {
        if (method_exists($resource, 'getTranslatableLocaleSwitcherOptions')) {
            return (array) $resource::getTranslatableLocaleSwitcherOptions();
        }

        $options = [];
        foreach ($this->resolveLocales($resource) as $locale) {
            $options[$locale] = $locale;
        }

        return $options;
    }

    public function resolveActiveLocale(string $resource, ?string $currentLocale = null): string
    {
        if (is_string($currentLocale) && $currentLocale !== '') {
            return $currentLocale;
        }

        $options = $this->resolveSwitcherOptions($resource);
        $sessionKey = $this->sessionKey($resource);
        $fromSession = session($sessionKey);

        if (is_string($fromSession) && array_key_exists($fromSession, $options)) {
            return $fromSession;
        }

        if (method_exists($resource, 'getDefaultTranslatableLocale')) {
            $default = $resource::getDefaultTranslatableLocale();

            if (is_string($default) && array_key_exists($default, $options)) {
                session()->put($sessionKey, $default);

                return $default;
            }
        }

        $appLocale = app()->getLocale();
        if (array_key_exists($appLocale, $options)) {
            session()->put($sessionKey, $appLocale);

            return $appLocale;
        }

        $first = array_key_first($options);
        $fallback = is_string($first) && $first !== '' ? $first : app()->getLocale();
        session()->put($sessionKey, $fallback);

        return $fallback;
    }

    public function rememberActiveLocale(string $resource, string $locale): void
    {
        session()->put($this->sessionKey($resource), $locale);
    }

    protected function sessionKey(string $resource): string
    {
        return 'filament-translatable.locale.' . str_replace('\\', '.', $resource);
    }
}
