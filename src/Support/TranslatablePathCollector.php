<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Component;

final class TranslatablePathCollector
{
    /**
     * @param array<int, mixed> $components
     * @return array<int, string>
     */
    public static function collect(array $components, string $prefix = ''): array
    {
        $collector = new self();

        return array_values(array_unique(
            $collector->fromComponents($components, $collector->segmentsIn($prefix))
        ));
    }

    /**
     * @param array<int, mixed> $components
     * @param array<int, string> $path
     * @return array<int, string>
     */
    private function fromComponents(array $components, array $path): array
    {
        $paths = [];

        foreach ($components as $component) {
            if ($component instanceof Component) {
                $paths = [...$paths, ...$this->fromComponent($component, $path)];
            }
        }

        return $paths;
    }

    /**
     * @param array<int, string> $path
     * @return array<int, string>
     */
    private function fromComponent(Component $component, array $path): array
    {
        if ($component instanceof Block) {
            return $this->fromComponents($this->childrenOf($component), $path);
        }

        $here = [...$path, ...$this->segmentsIn((string) $component->getStatePath(false))];

        if ($component instanceof Builder) {
            return $this->fromComponents(
                $this->childrenOf($component),
                [...$here, TranslatablePath::WILDCARD, 'data'],
            );
        }

        if ($component instanceof Repeater) {
            return $this->fromComponents(
                $this->childrenOf($component),
                [...$here, TranslatablePath::WILDCARD],
            );
        }

        $paths = ($here !== [] && TranslatableComponents::isMarked($component))
            ? [implode('.', $here)]
            : [];

        return [...$paths, ...$this->fromComponents($this->childrenOf($component), $here)];
    }

    /**
     * @return array<int, mixed>
     */
    private function childrenOf(Component $component): array
    {
        $children = $component->getDefaultChildComponents();

        return is_array($children) ? $children : [];
    }

    /**
     * @return array<int, string>
     */
    private function segmentsIn(string $path): array
    {
        return array_values(array_filter(
            explode('.', $path),
            static fn (string $segment): bool => $segment !== '',
        ));
    }
}
