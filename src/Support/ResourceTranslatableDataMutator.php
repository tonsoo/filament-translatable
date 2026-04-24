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
     * @param array<int, string> $translatableAttributes
     * @return array<string, mixed>
     */
    public function mutateForFill(array $data, array $translatableAttributes, string $activeLocale): array
    {
        foreach ($translatableAttributes as $attribute) {
            $translations = $this->normalizeTranslations($data[$attribute] ?? []);
            $data[$attribute] = $this->resolveSingleLocaleValueForFill($translations, $activeLocale);
        }

        return $this->normalizeData(
            $data,
            $translatableAttributes,
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
     * @param array<int, string> $translatableAttributes
     * @return array<string, mixed>
     */
    public function mutateForPersist(
        array $data,
        array $translatableAttributes,
        string $activeLocale,
        ?Model $record = null,
    ): array {
        foreach ($translatableAttributes as $attribute) {
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
            $translatableAttributes,
            $activeLocale,
            fn (mixed $value) => $this->normalizeTranslationMapForPersist($value, $activeLocale)
        );
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

        $raw = $record->getRawOriginal($attribute);

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
