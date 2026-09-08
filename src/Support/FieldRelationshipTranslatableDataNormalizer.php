<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Illuminate\Database\Eloquent\Model;
use Tonsoo\FilamentTranslatable\Concerns\NormalizesTranslatableData;
use Tonsoo\FilamentTranslatable\Concerns\UsesTranslationNormalizationChecks;

final class FieldRelationshipTranslatableDataNormalizer
{
    use NormalizesTranslatableData, UsesTranslationNormalizationChecks;

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     * @return array<string, mixed>
     */
    public function normalizeForFill(array $data, array|TranslatablePaths $translatableAttributes, string $activeLocale): array
    {
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
     * @param array<int, string>|TranslatablePaths $translatableAttributes
     * @return array<string, mixed>
     */
    public function normalizeForPersist(
        array $data,
        array|TranslatablePaths $translatableAttributes,
        string $activeLocale,
        ?Model $record = null,
    ): array
    {
        return $this->normalizeData(
            $data,
            $translatableAttributes,
            $activeLocale,
            function (mixed $value, mixed $key) use ($record, $activeLocale) {
                $translations = $this->normalizeTranslationMapForPersist($value, $activeLocale);

                if ($record instanceof Model) {
                    $currentTranslations = $this->getRecordTranslations($record, $key);
                    if ($currentTranslations !== []) {
                        $translations = array_replace($currentTranslations, $translations);
                    }
                }

                return $translations;
            }
        );
    }

    /**
     * @return array<string, string|null>
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
