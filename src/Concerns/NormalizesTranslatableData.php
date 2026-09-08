<?php

namespace Tonsoo\FilamentTranslatable\Concerns;

use Tonsoo\FilamentTranslatable\Support\TranslatablePaths;

trait NormalizesTranslatableData
{
    use UsesFallbackTranslations;

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     * @param callable(mixed, string|int, array<int, string|int>): mixed $normalizeTranslatableValue
     * @param array<int, string|int> $path
     * @return array<string, mixed>
     */
    protected function normalizeData(
        array $data,
        array|TranslatablePaths $translatableAttributes,
        string $activeLocale,
        callable $normalizeTranslatableValue,
        array $path = [],
    ): array {
        $paths = $translatableAttributes instanceof TranslatablePaths
            ? $translatableAttributes
            : TranslatablePaths::make($translatableAttributes);

        foreach ($data as $key => $value) {
            $here = [...$path, $key];

            if ($paths->isExcluded($here)) {
                continue;
            }

            if ($paths->matches($here)) {
                $data[$key] = $normalizeTranslatableValue($value, $key, $here);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->normalizeData(
                    $value,
                    $paths,
                    $activeLocale,
                    $normalizeTranslatableValue,
                    $here,
                );
            }
        }

        return $data;
    }

    protected function normalizeSingleLocaleScalar(mixed $value, string $activeLocale): mixed
    {
        do {
            $old = $value;
            $value = $this->resolveSingleLocaleIncomingValue($value, $activeLocale);
        } while ($value !== $old);

        return is_array($value) ? null : $value;
    }

    protected function resolveSingleLocaleIncomingValue(mixed $incoming, string $activeLocale): mixed
    {
        if (! is_array($incoming)) {
            return $incoming;
        }

        if (array_key_exists($activeLocale, $incoming)) {
            return $incoming[$activeLocale];
        }

        $fallback = $this->getFallbackTranslationValue($incoming);
        if ($fallback !== null) {
            return $fallback;
        }

        foreach ($incoming as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function normalizeTranslatableValue(mixed $value, string $activeLocale): ?string
    {
        $resolved = $this->normalizeSingleLocaleScalar($value, $activeLocale);

        if ($resolved === null || $resolved === '') {
            return null;
        }

        return is_string($resolved) ? $resolved : (string) $resolved;
    }
}
