<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use InvalidArgumentException;

final class TranslatablePath
{
    public const WILDCARD = '*';

    /**
     * @param array<int, string> $segments
     */
    private function __construct(private readonly array $segments) {}

    public static function make(string $declaration): self
    {
        return new self(array_values(array_filter(
            array_map('trim', explode('.', $declaration)),
            static fn (string $segment): bool => $segment !== '',
        )));
    }

    public function column(): string
    {
        return $this->segments[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * @return array<int, string>
     */
    public function segmentsInsideColumn(): array
    {
        return array_slice($this->segments, 1);
    }

    public function isEmpty(): bool
    {
        return $this->segments === [];
    }

    public function isNested(): bool
    {
        return count($this->segments) > 1;
    }

    public function hasWildcard(): bool
    {
        return in_array(self::WILDCARD, $this->segments, true);
    }

    /**
     * @param array<int, string|int> $path
     */
    public function matches(array $path): bool
    {
        if (count($path) !== count($this->segments)) {
            return false;
        }

        return $this->segmentsMatch($path);
    }

    /**
     * @param array<int, string|int> $path
     */
    public function isReachedThrough(array $path): bool
    {
        if (count($path) >= count($this->segments)) {
            return false;
        }

        return $this->segmentsMatch($path);
    }

    public function assertAddressesOneValue(): void
    {
        if ($this->hasWildcard()) {
            throw new InvalidArgumentException(
                "The path [{$this}] matches more than one value. Address one of them without a wildcard."
            );
        }
    }

    public function __toString(): string
    {
        return implode('.', $this->segments);
    }

    /**
     * @param array<int, string|int> $path
     */
    private function segmentsMatch(array $path): bool
    {
        foreach ($path as $index => $key) {
            if ($this->segments[$index] === self::WILDCARD) {
                continue;
            }

            if ((string) $key !== $this->segments[$index]) {
                return false;
            }
        }

        return true;
    }
}
