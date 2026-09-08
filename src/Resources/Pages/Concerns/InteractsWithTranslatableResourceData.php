<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Resources\Pages\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Tonsoo\FilamentTranslatable\Support\ModelTranslatableAttributesResolver;
use Tonsoo\FilamentTranslatable\Support\ResourceTranslatableDataMutator;
use Tonsoo\FilamentTranslatable\Support\TranslatablePaths;
use Tonsoo\FilamentTranslatable\Support\TranslatableResourceLocaleResolver;

trait InteractsWithTranslatableResourceData
{
    public ?string $translatableLocale = null;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->inTheTranslatableLocale(fn (string $locale): array => $this->dataMutator()->mutateForFill(
            $data,
            $this->getTranslatablePaths(),
            $locale,
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mutateTranslatableDataBeforePersist($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
        $record = $record instanceof Model ? $record : null;

        return $this->mutateTranslatableDataBeforePersist($data, $record);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateTranslatableDataBeforePersist(array $data, ?Model $record = null): array
    {
        return $this->inTheTranslatableLocale(fn (string $locale): array => $this->dataMutator()->mutateForPersist(
            $data,
            $this->getTranslatablePaths(),
            $locale,
            $record,
        ));
    }

    /**
     * @param Closure(string): mixed $callback
     */
    protected function inTheTranslatableLocale(Closure $callback): mixed
    {
        $previous = app()->getLocale();
        $active = $this->getActiveTranslatableLocale();

        app()->setLocale($active);

        try {
            return $callback($active);
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function getTranslatableAttributes(): array
    {
        $modelClass = $this->resolveResourceModelClass();
        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return [];
        }

        return $this->modelResolver()->resolve(new $modelClass());
    }

    protected function getTranslatablePaths(): TranslatablePaths
    {
        $modelClass = $this->resolveResourceModelClass();

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return TranslatablePaths::make([]);
        }

        return $this->modelResolver()->paths(new $modelClass());
    }

    /**
     * @return array<int, string>
     */
    protected function getTranslatableLocales(): array
    {
        return $this->localeResolver()->resolveLocales(static::getResource());
    }

    protected function hasTranslatableLocaleSwitcher(): bool
    {
        return $this->localeResolver()->hasLocaleSwitcher(static::getResource());
    }

    /**
     * @return array<string, string>
     */
    protected function getTranslatableLocaleSwitcherOptions(): array
    {
        return $this->localeResolver()->resolveSwitcherOptions(static::getResource());
    }

    protected function getActiveTranslatableLocale(): string
    {
        $this->translatableLocale = $this->localeResolver()->resolveActiveLocale(
            static::getResource(),
            $this->translatableLocale,
        );

        return $this->translatableLocale;
    }

    public function updatedTranslatableLocale(?string $locale): void
    {
        $options = $this->getTranslatableLocaleSwitcherOptions();
        if (! is_string($locale) || ! array_key_exists($locale, $options)) {
            return;
        }

        $this->translatableLocale = $locale;
        $this->localeResolver()->rememberActiveLocale(static::getResource(), $locale);

        $this->inTheTranslatableLocale(function (): void {
            $this->refillFormForTranslatableLocale();
        });
    }

    protected function refillFormForTranslatableLocale(): void
    {
        if (method_exists($this, 'getRecord') && method_exists($this, 'fillFormWithDataAndCallHooks')) {
            $record = $this->getRecord();

            if ($record instanceof Model) {
                $this->fillFormWithDataAndCallHooks($record);

                return;
            }
        }

        if (method_exists($this, 'fillForm')) {
            $this->fillForm();
        }
    }

    public function getHeading(): string|Htmlable
    {
        $heading = parent::getHeading();

        if (! $this->hasTranslatableLocaleSwitcher()) {
            return $heading;
        }

        $options = $this->getTranslatableLocaleSwitcherOptions();
        if (count($options) <= 1) {
            return $heading;
        }

        $active = $this->getActiveTranslatableLocale();

        return new HtmlString(
            view('filament-translatable::components.heading-locale-switcher', [
                'heading' => $heading,
                'options' => $options,
                'activeLocale' => $active,
            ])->render()
        );
    }

    protected function resolveResourceModelClass(): ?string
    {
        $resource = static::getResource();
        if (method_exists($resource, 'getModel')) {
            $model = $resource::getModel();

            return is_string($model) ? $model : null;
        }

        if (property_exists($resource, 'model')) {
            return is_string($resource::$model) ? $resource::$model : null;
        }

        return null;
    }

    protected function dataMutator(): ResourceTranslatableDataMutator
    {
        return new ResourceTranslatableDataMutator();
    }

    protected function localeResolver(): TranslatableResourceLocaleResolver
    {
        return new TranslatableResourceLocaleResolver();
    }

    protected function modelResolver(): ModelTranslatableAttributesResolver
    {
        return new ModelTranslatableAttributesResolver();
    }
}
