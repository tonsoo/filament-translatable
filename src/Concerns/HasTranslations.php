<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Concerns;

use Illuminate\Support\Arr;
use InvalidArgumentException;

trait HasTranslations
{
    use UsesFallbackTranslations, UsesTranslationNormalizationChecks;

    public function getAttribute($key): mixed
    {
        if (! is_string($key)) {
            return parent::getAttribute($key);
        }

        if (in_array($key, $this->getTranslatableAttributes(), true)) {
            return $this->getTranslation($key, app()->getLocale());
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

        if (! in_array($key, $this->getTranslatableAttributes(), true)) {
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

    public function initializeHasTranslations(): void
    {
        foreach ($this->getTranslatableAttributes() as $attribute) {
            if (! isset($this->casts[$attribute])) {
                $this->casts[$attribute] = 'array';
            }
        }
    }

    public function setTranslation(string $attribute, string $locale, ?string $value): static
    {
        $this->assertAttributeIsTranslatable($attribute);

        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        $this->attributes[$attribute] = $this->asJson($translations);

        return $this;
    }

    /**
     * @param array<string, string|null> $translations
     */
    public function setTranslations(string $attribute, array $translations): static
    {
        $this->assertAttributeIsTranslatable($attribute);

        $this->attributes[$attribute] = $this->asJson($translations);

        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getTranslations(string $attribute): array
    {
        $this->assertAttributeIsTranslatable($attribute);

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

    protected function assertAttributeIsTranslatable(string $attribute): void
    {
        if (! in_array($attribute, $this->getTranslatableAttributes(), true)) {
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
        if (! in_array($attribute, $this->getTranslatableAttributes(), true) || $locale === '') {
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
