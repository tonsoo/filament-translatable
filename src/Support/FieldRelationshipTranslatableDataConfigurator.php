<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

final class FieldRelationshipTranslatableDataConfigurator
{
    public function __construct(
        protected ModelTranslatableAttributesResolver $resolver,
        protected FieldRelationshipTranslatableDataNormalizer $normalizer,
    ) {}

    public function configure(): void
    {
        if (! class_exists(Field::class) || ! method_exists(Field::class, 'configureUsing')) {
            return;
        }

        Field::configureUsing(fn (Field $field) => $this->handleFieldConfiguration($field));
    }

    protected function handleFieldConfiguration(Field $field): void
    {
        if (! method_exists($field, 'getRelationship')) {
            return;
        }

        $this->configureFieldMutationMethod($field, 'mutateRelationshipDataBeforeFillUsing', willPersist: false);
        $this->configureFieldMutationMethod($field, 'mutateRelationshipDataBeforeCreateUsing');
        $this->configureFieldMutationMethod($field, 'mutateRelationshipDataBeforeSaveUsing');
    }

    protected function configureFieldMutationMethod(Field $field, string $methodName, bool $willPersist = true): void
    {
        if (! method_exists($field, $methodName)) {
            return;
        }

        $field->{$methodName}(function (?array $data = [], $record = null) use ($field, $methodName, $willPersist): array {
            return $this->resolveAttributesForFieldDataMutation(
                $field,
                $data ?? [],
                $willPersist,
                $record instanceof Model ? $record : null
            );
        });
    }

    protected function resolveAttributesForFieldDataMutation(Field $field, array $data, bool $willPersist, ?Model $record = null): array
    {
        $translatable = $this->resolveTranslatableAttributes($field);
        if ($translatable === []) {
            return $data;
        }

        if ($willPersist) {
            return $this->normalizer->normalizeForPersist(
                $data,
                $translatable,
                $this->resolveActiveLocale(),
                $record,
            );
        } else {
            return $this->normalizer->normalizeForFill(
                $data,
                $translatable,
                $this->resolveActiveLocale(),
            );
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTranslatableAttributes(Field $field): array
    {
        try {
            $relationship = $field->getRelationship();
        } catch (Throwable) {
            return [];
        }

        if (! $relationship instanceof Relation) {
            return [];
        }

        return $this->resolver->resolve($relationship->getRelated());
    }

    protected function resolveActiveLocale(): string
    {
        $locale = app()->getLocale();

        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }
}
