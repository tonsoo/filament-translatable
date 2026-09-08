<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

final class TranslatablePaths
{
    /** @var array<int, string> */
    private array $columns = [];

    /** @var array<int, TranslatablePath> */
    private array $nestedPaths = [];

    /** @var array<int, TranslatablePath> */
    private array $excludedPaths = [];

    /** @var array<int, string> */
    private array $structuredColumns = [];

    /**
     * @param array<int, string> $translatable
     * @param array<int, string> $except
     */
    public static function make(array $translatable, array $except = []): self
    {
        $paths = new self();

        foreach (self::parse($translatable) as $path) {
            if ($path->isNested()) {
                $paths->nestedPaths[] = $path;
                $paths->structuredColumns[] = $path->column();

                continue;
            }

            $paths->columns[] = $path->column();
        }

        $paths->excludedPaths = self::parse($except);
        $paths->columns = array_values(array_unique($paths->columns));
        $paths->structuredColumns = array_values(array_unique($paths->structuredColumns));

        return $paths;
    }

    /**
     * @param array<int, string|int> $path
     */
    public function matches(array $path): bool
    {
        if ($path === [] || $this->isExcluded($path)) {
            return false;
        }

        foreach ($this->nestedPaths as $nested) {
            if ($nested->matches($path)) {
                return true;
            }
        }

        if ($this->isStructuredColumn((string) $path[0])) {
            return false;
        }

        return in_array((string) $path[array_key_last($path)], $this->columns, true);
    }

    /**
     * @param array<int, string|int> $path
     */
    public function isExcluded(array $path): bool
    {
        foreach ($this->excludedPaths as $excluded) {
            if ($excluded->matches($path) || $excluded->isReachedThrough($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, string>
     */
    public function structuredColumns(): array
    {
        return $this->structuredColumns;
    }

    public function isStructuredColumn(string $column): bool
    {
        return in_array($column, $this->structuredColumns, true);
    }

    public function isDeclared(string $declaration): bool
    {
        $path = TranslatablePath::make($declaration);

        if ($path->isEmpty()) {
            return false;
        }

        if (! $path->isNested()) {
            return in_array($path->column(), $this->columns, true);
        }

        foreach ($this->nestedPaths as $nested) {
            if ($nested->matches($path->segments())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $declarations
     * @return array<int, TranslatablePath>
     */
    private static function parse(array $declarations): array
    {
        $paths = [];

        foreach ($declarations as $declaration) {
            if (! is_string($declaration) || trim($declaration) === '') {
                continue;
            }

            $path = TranslatablePath::make($declaration);

            if (! $path->isEmpty()) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
