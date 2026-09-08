<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Filament\Schemas\Components\Component;
use WeakMap;

final class TranslatableComponents
{
    /** @var WeakMap<Component, bool>|null */
    private static ?WeakMap $marked = null;

    public static function mark(Component $component, bool $condition = true): void
    {
        self::marked()[$component] = $condition;
    }

    public static function isMarked(Component $component): bool
    {
        return self::marked()[$component] ?? false;
    }

    public static function forget(): void
    {
        self::$marked = null;
    }

    /**
     * @return WeakMap<Component, bool>
     */
    private static function marked(): WeakMap
    {
        return self::$marked ??= new WeakMap();
    }
}
