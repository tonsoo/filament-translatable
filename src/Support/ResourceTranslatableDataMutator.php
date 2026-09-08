<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Illuminate\Database\Eloquent\Model;
use Tonsoo\FilamentTranslatable\Concerns\NormalizesTranslatableData;
use Tonsoo\FilamentTranslatable\Concerns\UsesFallbackTranslations;
use Tonsoo\FilamentTranslatable\Concerns\UsesTranslationNormalizationChecks;

final class ResourceTranslatableDataMutator
{
    use NormalizesTranslatableData, UsesFallbackTranslations, UsesTranslationNormalizationChecks;

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     * @return array<string, mixed>
     */
    public function mutateForFill(
        array $data,
        array|TranslatablePaths $translatableAttributes,
        string $activeLocale,
    ): array {
        $paths = $this->paths($translatableAttributes);

        foreach ($paths->columns() as $attribute) {
            $translations = $this->normalizeTranslations($data[$attribute] ?? []);
            $data[$attribute] = $this->resolveSingleLocaleValueForFill($translations, $activeLocale);
        }

        return $this->normalizeData(
            $data,
            $paths,
            $activeLocale,
            function (mixed $value) use ($activeLocale) {
                $resolved = $this->normalizeSingleLocaleScalar(
                    $this->resolveSingleLocaleIncomingValue($value, $activeLocale),
                    $activeLocale,
                );

                return $resolved === '' ? null : $resolved;
            }
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     * @return array<string, mixed>
     */
    public function mutateForPersist(
        array $data,
        array|TranslatablePaths $translatableAttributes,
        string $activeLocale,
        ?Model $record = null,
    ): array {
        $paths = $this->paths($translatableAttributes);

        foreach ($paths->columns() as $attribute) {
            $value = $data[$attribute] ?? null;
            $translations = $this->normalizeTranslationMapForPersist($value, $activeLocale);

            if ($record instanceof Model) {
                $currentTranslations = $this->getRecordTranslations($record, $attribute);
                if ($currentTranslations !== []) {
                    $translations = array_replace($currentTranslations, $translations);
                }
            }

            $data[$attribute] = $translations;
        }

        return $this->normalizeData(
            $data,
            $paths,
            $activeLocale,
            function (mixed $value, mixed $key, array $path = []) use ($activeLocale, $record) {
                $translations = $this->normalizeTranslationMapForPersist($value, $activeLocale);

                if (! $record instanceof Model) {
                    return $translations;
                }

                $current = count($path) > 1
                    ? $this->getRecordTranslationsAtPath($record, $path)
                    : $this->getRecordTranslations($record, (string) $key);

                return $current === [] ? $translations : array_replace($current, $translations);
            }
        );
    }

    /**
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     */
    private function paths(array|TranslatablePaths $translatableAttributes): TranslatablePaths
    {
        return $translatableAttributes instanceof TranslatablePaths
            ? $translatableAttributes
            : TranslatablePaths::make($translatableAttributes);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function normalizeTranslations(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function resolveSingleLocaleValueForFill(array $translations, string $activeLocale): mixed
    {
        $value = $translations[$activeLocale] ?? null;

        if ($value === null || $value === '') {
            $fallbackValue = $this->getFallbackTranslationValue($translations);
            if ($fallbackValue !== null && $fallbackValue !== '') {
                $value = $fallbackValue;
            }
        }

        return $this->normalizeSingleLocaleScalar($value, $activeLocale);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeTranslationMapForPersist(mixed $value, string $activeLocale): array
    {
        if (is_array($value) && $this->isAssociativeLocaleMap($value)) {
            return $this->normalizeAssociativeLocaleMapForPersist($value, $activeLocale);
        }

        return [
            $activeLocale => $this->normalizeTranslatableValue(
                $this->resolveSingleLocaleIncomingValue($value, $activeLocale),
                $activeLocale,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    protected function normalizeAssociativeLocaleMapForPersist(array $value, string $activeLocale): array
    {
        $translations = [];

        foreach ($value as $locale => $translatedValue) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $translations[$locale] = $this->normalizeTranslatableValue($translatedValue, $activeLocale);
        }

        return $translations;
    }

    /**
     * @return array<string, string|null>
     */
    protected function getRecordTranslations(Model $record, string $attribute): array
    {
        if (method_exists($record, 'getTranslations')) {
            $translations = $record->getTranslations($attribute);

            return is_array($translations) ? $translations : [];
        }

        return $this->decodeColumn($record, $attribute);
    }

    /**
     * @param array<int, string|int> $path
     * @return array<string, string|null>
     */
    protected function getRecordTranslationsAtPath(Model $record, array $path): array
    {
        $stored = $this->decodeColumn($record, (string) $path[0]);

        if ($stored === []) {
            return [];
        }

        $value = data_get($stored, array_map('strval', array_slice($path, 1)));

        return is_array($value) && $this->isAssociativeLocaleMap($value) ? $value : [];
    }

    /**
     * @return array<mixed>
     */
    protected function decodeColumn(Model $record, string $column): array
    {
        $raw = $record->getRawOriginal($column);

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
