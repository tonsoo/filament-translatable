<?php

namespace Tonsoo\FilamentTranslatable\Concerns;

trait UsesTranslationNormalizationChecks
{
    /**
     * @param array<mixed> $value
     */
    protected function isAssociativeLocaleMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || $key === '') {
                return false;
            }
        }

        return true;
    }
}