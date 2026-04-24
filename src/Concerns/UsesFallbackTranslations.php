<?php

namespace Tonsoo\FilamentTranslatable\Concerns;

trait UsesFallbackTranslations
{
    protected function getFallbackTranslationValue(array $translations = []): mixed
    {
        $fallbackLocale = config('app.fallback_locale');
        if (is_string($fallbackLocale) && $fallbackLocale !== '' && array_key_exists($fallbackLocale, $translations)) {
            return $translations[$fallbackLocale];
        }
        return null;
    }
}