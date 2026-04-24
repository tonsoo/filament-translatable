<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Resources\Concerns;

trait HasTranslatableLocaleSwitcher
{
    public static function hasTranslatableLocaleSwitcher(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function getTranslatableLocales(): array
    {
        $locales = static::getTranslatableLocalesProperty();
        if ($locales !== null && $locales !== []) {
            return $locales;
        }

        $availableLocales = config('app.available_locales');
        if (! is_array($availableLocales) || $availableLocales === []) {
            $availableLocales = config('app.locales');
        }

        if (is_array($availableLocales)) {
            $locales = array_values(array_unique(array_filter(array_map('strval', $availableLocales))));
            if ($locales !== []) {
                return $locales;
            }
        }

        $appLocale = (string) app()->getLocale();

        return $appLocale !== '' ? [$appLocale] : [];
    }

    /**
     * @return array<string, string>
     */
    public static function getTranslatableLocaleSwitcherOptions(): array
    {
        $labels = static::getTranslatableLabelsProperty();

        $options = [];

        foreach (static::getTranslatableLocales() as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $options[$locale] = (string) ($labels[$locale] ?? $locale);
        }

        return $options;
    }

    public static function getDefaultTranslatableLocale(): ?string
    {
        $locale = static::getStaticProperty('defaultTranslatableLocale');
        if (!is_string($locale) || $locale === '') {
            return null;
        }
        return $locale;
    }

    protected static function getTranslatableLocalesProperty(): ?array
    {
        $translatable = static::getStaticProperty('translatableLocales');
        if (!is_array($translatable)) {
            return null;
        }
        return array_values(array_unique(array_filter(array_map('strval', $translatable))));
    }

    protected static function getTranslatableLabelsProperty(): ?array
    {
        $labels = static::getStaticProperty('translatableLocaleLabels');
        if (!is_array($labels)) {
            return null;
        }
        return $labels;
    }

    protected static function getStaticProperty(string $property): mixed
    {
        if (! property_exists(static::class, $property)) {
            return null;
        }

        return static::$$property;
    }
}
