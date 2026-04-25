<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Database\Eloquent;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression as QueryExpression;

class TranslatableBuilder extends Builder
{
    protected bool $applyTranslatableLocaleConstraints = true;

    public function withoutAutoTranslationsQuery(): static
    {
        $this->applyTranslatableLocaleConstraints = false;

        return $this;
    }

    public function withAutoTranslationsQuery(): static
    {
        $this->applyTranslatableLocaleConstraints = true;

        return $this;
    }

    /**
     * @param  (\Closure(static): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (! ($column instanceof \Closure && is_null($operator))) {
            $column = $this->mapColumnReference($column);
            $column = $this->applyLikeCollationToColumnForWhere($column, $operator);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function __call($method, $parameters)
    {
        $normalized = strtolower($method);
        if (
            (str_starts_with($normalized, 'where') || str_starts_with($normalized, 'orwhere'))
            && ! in_array($normalized, ['whereraw', 'orwhereraw'], true)
            && array_key_exists(0, $parameters)
        ) {
            $parameters[0] = $this->mapColumnReference($parameters[0]);

            if (in_array($normalized, ['wherelike', 'orwherelike', 'wherenotlike', 'orwherenotlike'], true)) {
                $caseSensitive = (bool) ($parameters[2] ?? false);
                $parameters[0] = $this->applyLikeCollationToColumn($parameters[0], $caseSensitive);
            }
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @param  mixed  $column
     */
    protected function mapColumnReference(mixed $column): mixed
    {
        if ($column instanceof Expression) {
            return $column;
        }

        if (is_array($column)) {
            $mapped = [];

            foreach ($column as $key => $value) {
                if (is_string($key)) {
                    $mapped[$this->mapSingleColumnReference($key)] = $value;

                    continue;
                }

                if (is_array($value) && isset($value[0]) && is_string($value[0])) {
                    $value[0] = $this->mapSingleColumnReference($value[0]);
                }

                $mapped[$key] = $value;
            }

            return $mapped;
        }

        if (! is_string($column)) {
            return $column;
        }

        return $this->mapSingleColumnReference($column);
    }

    protected function mapSingleColumnReference(string $column): string
    {
        if (! $this->shouldMapTranslatableColumns()) {
            return $column;
        }

        if (str_contains($column, '->')) {
            return $column;
        }

        $model = $this->getModel();
        $translatableAttributes = $this->resolveTranslatableAttributes($model);
        if ($translatableAttributes === []) {
            return $column;
        }

        [$columnWithoutLocale, $explicitLocale] = $this->extractColumnAndLocale($column, $translatableAttributes);
        if (! in_array($this->extractAttributeName($columnWithoutLocale), $translatableAttributes, true)) {
            return $column;
        }

        $locale = $explicitLocale ?: $this->resolveQueryLocale($model);
        if ($locale === '') {
            return $column;
        }

        return "{$columnWithoutLocale}->{$locale}";
    }

    protected function shouldMapTranslatableColumns(): bool
    {
        if (! $this->applyTranslatableLocaleConstraints) {
            return false;
        }

        $model = $this->getModel();
        return method_exists($model, 'getTranslatableAttributes');
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTranslatableAttributes(Model $model): array
    {
        $attributes = $model->getTranslatableAttributes();

        return array_values(array_filter($attributes, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @param  array<int, string>  $translatableAttributes
     * @return array{0: string, 1: string|null}
     */
    protected function extractColumnAndLocale(string $column, array $translatableAttributes): array
    {
        $segments = explode('.', $column);
        if (count($segments) < 2) {
            return [$column, null];
        }

        $attribute = $segments[count($segments) - 2];
        $locale = $segments[count($segments) - 1];
        if ($locale !== '' && in_array($attribute, $translatableAttributes, true)) {
            $columnWithoutLocale = implode('.', array_slice($segments, 0, -1));

            return [$columnWithoutLocale, $locale];
        }

        return [$column, null];
    }

    protected function extractAttributeName(string $column): string
    {
        $segments = explode('.', $column);

        return (string) end($segments);
    }

    protected function resolveQueryLocale(Model $model): string
    {
        if (method_exists($model, 'getQueryLocaleForTranslations')) {
            return (string) $model->getQueryLocaleForTranslations();
        }

        return (string) app()->getLocale();
    }

    protected function applyLikeCollationToColumnForWhere(mixed $column, mixed $operator): mixed
    {
        if (! is_string($operator)) {
            return $column;
        }

        $normalizedOperator = strtolower(trim($operator));
        if (! in_array($normalizedOperator, ['like', 'not like'], true)) {
            return $column;
        }

        return $this->applyLikeCollationToColumn($column, false);
    }

    protected function applyLikeCollationToColumn(mixed $column, bool $caseSensitive): mixed
    {
        if ($caseSensitive || ! is_string($column)) {
            return $column;
        }

        if (! str_contains($column, '->')) {
            return $column;
        }

        $connection = $this->getQuery()->getConnection();
        if ($connection->getDriverName() !== 'mysql') {
            return $column;
        }

        $collation = $this->resolveLikeCollation($connection->getConfig('collation'));
        if ($collation === null) {
            return $column;
        }

        $wrappedColumn = $this->getQuery()->getGrammar()->wrap($column);

        return new QueryExpression("{$wrappedColumn} collate {$collation}");
    }

    protected function resolveLikeCollation(mixed $connectionCollation): ?string
    {
        if (is_string($connectionCollation) && preg_match('/^[A-Za-z0-9_]+$/', $connectionCollation) === 1) {
            return $connectionCollation;
        }

        return 'utf8mb4_unicode_ci';
    }
}
