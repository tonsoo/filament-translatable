<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Concerns;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Tonsoo\FilamentTranslatable\Database\Eloquent\TranslatableBuilder;
use Tonsoo\FilamentTranslatable\Support\TranslatablePath;
use Tonsoo\FilamentTranslatable\Support\TranslatablePaths;

trait HasTranslations
{
    use UsesFallbackTranslations, UsesTranslationNormalizationChecks;

    public function getAttribute($key): mixed
    {
        if (! is_string($key)) {
            return parent::getAttribute($key);
        }

        if (in_array($key, $this->translatablePaths()->columns(), true)) {
            return $this->getTranslation($key, app()->getLocale());
        }

        if ($this->translatablePaths()->isStructuredColumn($key)) {
            return $this->resolveNestedTranslations($key, parent::getAttribute($key), app()->getLocale());
        }

        [$attribute, $locale] = $this->parseTranslatablePath($key);
        if ($attribute !== null && $locale !== null) {
            return Arr::get($this->getTranslations($attribute), $locale);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value): static
    {
        if (! is_string($key)) {
            return parent::setAttribute($key, $value);
        }

        [$attribute, $locale] = $this->parseTranslatablePath($key);
        if ($attribute !== null && $locale !== null) {
            return $this->setTranslation($attribute, $locale, $this->normalizeTranslatableValue($value));
        }

        if ($this->translatablePaths()->isStructuredColumn($key) && is_array($value)) {
            return parent::setAttribute($key, $this->mergeNestedTranslations($key, $value, app()->getLocale()));
        }

        if (! in_array($key, $this->translatablePaths()->columns(), true)) {
            return parent::setAttribute($key, $value);
        }

        if (! is_array($value)) {
            return $this->setTranslation($key, app()->getLocale(), $this->normalizeTranslatableValue($value));
        }

        if (! $this->isAssociativeLocaleMap($value)) {
            throw new InvalidArgumentException(
                "Invalid translation payload for [{$key}]. ".
                "Use dot notation (e.g. {$key}.en) or an associative locale map."
            );
        }

        $translations = [];
        foreach ($value as $locale => $translatedValue) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $translations[$locale] = $this->normalizeTranslatableValue($translatedValue);
        }

        return $this->setTranslations($key, $translations);
    }

    /**
     * @return array<int, string>
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? (array) $this->translatable : [];
    }

    /** @var array<class-string, TranslatablePaths> */
    private static array $translatablePathsPerModel = [];

    /**
     * @return array<int, string>
     */
    public function getTranslatableExcept(): array
    {
        return property_exists($this, 'translatableExcept') ? (array) $this->translatableExcept : [];
    }

    public function translatablePaths(): TranslatablePaths
    {
        return self::$translatablePathsPerModel[static::class] ??= TranslatablePaths::make(
            $this->getTranslatableAttributes(),
            $this->getTranslatableExcept(),
        );
    }

    public function initializeHasTranslations(): void
    {
        $paths = $this->translatablePaths();

        foreach ([...$paths->columns(), ...$paths->structuredColumns()] as $column) {
            if (! isset($this->casts[$column])) {
                $this->casts[$column] = 'array';
            }
        }
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        return $this->setTranslations($attribute, $translations);
    }

    /**
     * @param array<string, string|null> $translations
     */
    public function setTranslations(string $attribute, array $translations): static
    {
        $this->assertAttributeIsTranslatable($attribute);

        $path = TranslatablePath::make($attribute);

        if (! $path->isNested()) {
            $this->attributes[$attribute] = $this->asJson($translations);

            return $this;
        }

        $path->assertAddressesOneValue();

        $column = $this->getAttributeValue($path->column());
        $column = is_array($column) ? $column : [];

        data_set($column, $path->segmentsInsideColumn(), $translations);

        $this->attributes[$path->column()] = $this->asJson($column);

        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getTranslations(string $attribute): array
    {
        $this->assertAttributeIsTranslatable($attribute);

        $path = TranslatablePath::make($attribute);

        if ($path->isNested()) {
            $path->assertAddressesOneValue();

            $value = data_get($this->getAttributeValue($path->column()), $path->segmentsInsideColumn());

            return is_array($value) ? $value : [];
        }

        $value = $this->getAttributeValue($attribute);

        if (! is_array($value)) {
            return [];
        }

        return $value;
    }

    public function getTranslation(string $attribute, string $locale, bool $fallback = true): ?string
    {
        $translations = $this->getTranslations($attribute);

        $exact = Arr::get($translations, $locale);
        if ($exact !== null && $exact !== '') {
            return (string) $exact;
        }

        if (! $fallback) {
            return null;
        }

        $fallbackValue = $this->getFallbackTranslationValue($translations);
        if ($fallbackValue === null || $fallbackValue === '') {
            return null;
        }

        return (string) $fallbackValue;
    }

    public function newEloquentBuilder($query): \Illuminate\Database\Eloquent\Builder
    {
        $builder = parent::newEloquentBuilder($query);

        if ($builder instanceof TranslatableBuilder) {
            return $builder;
        }

        if (get_class($builder) !== \Illuminate\Database\Eloquent\Builder::class) {
            return $builder;
        }

        return new TranslatableBuilder($query);
    }

    public function getQueryLocaleForTranslations(): string
    {
        return (string) app()->getLocale();
    }

    protected function resolveNestedTranslations(string $column, mixed $value, string $locale): mixed
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return $value;
        }

        return $this->walkNestedTranslations(
            $decoded,
            [$column],
            fn (mixed $translations): mixed => $this->translationFor($translations, $locale),
        );
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    protected function mergeNestedTranslations(string $column, array $value, string $locale): array
    {
        $stored = $this->getAttributeValue($column);
        $stored = is_array($stored) ? $stored : [];

        return $this->walkNestedTranslations(
            $value,
            [$column],
            function (mixed $incoming, array $path) use ($stored, $locale): array {
                $current = data_get($stored, array_map('strval', array_slice($path, 1)));
                $translations = is_array($current) && $this->isAssociativeLocaleMap($current) ? $current : [];

                if (is_array($incoming) && $this->isAssociativeLocaleMap($incoming)) {
                    return array_replace($translations, $incoming);
                }

                $translations[$locale] = $this->normalizeTranslatableValue($incoming);

                return $translations;
            },
        );
    }

    /**
     * @param array<string, mixed> $value
     * @param array<int, string|int> $path
     * @param callable(mixed, array<int, string|int>): mixed $translate
     * @return array<string, mixed>
     */
    private function walkNestedTranslations(array $value, array $path, callable $translate): array
    {
        $paths = $this->translatablePaths();

        foreach ($value as $key => $item) {
            $here = [...$path, $key];

            if ($paths->matches($here)) {
                $value[$key] = $translate($item, $here);

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->walkNestedTranslations($item, $here, $translate);
            }
        }

        return $value;
    }

    private function translationFor(mixed $translations, string $locale): mixed
    {
        if (! is_array($translations)) {
            return $translations;
        }

        $exact = $translations[$locale] ?? null;
        if ($exact !== null && $exact !== '') {
            return (string) $exact;
        }

        $fallback = $this->getFallbackTranslationValue($translations);

        return $fallback === null || $fallback === '' ? null : (string) $fallback;
    }

    protected function assertAttributeIsTranslatable(string $attribute): void
    {
        if (! $this->translatablePaths()->isDeclared($attribute)) {
            throw new InvalidArgumentException("Attribute [{$attribute}] is not marked as translatable.");
        }
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function parseTranslatablePath(string $key): array
    {
        $segments = explode('.', $key, 2);
        if (count($segments) !== 2) {
            return [null, null];
        }

        [$attribute, $locale] = $segments;
        if (! in_array($attribute, $this->translatablePaths()->columns(), true) || $locale === '') {
            return [null, null];
        }

        return [$attribute, $locale];
    }

    protected function normalizeTranslatableValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
