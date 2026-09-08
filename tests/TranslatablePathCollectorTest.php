<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Tonsoo\FilamentTranslatable\Support\TranslatablePathCollector;
use Tonsoo\FilamentTranslatable\Support\TranslatablePaths;

it('collects the fields a schema marks', function (): void {
    $paths = TranslatablePathCollector::collect([
        TextInput::make('heading')->translatable(),
        TextInput::make('image'),
    ], 'content');

    expect($paths)->toBe(['content.heading']);
});

it('collects nothing when no field is marked', function (): void {
    $paths = TranslatablePathCollector::collect([
        TextInput::make('heading'),
        TextInput::make('image'),
    ], 'content');

    expect($paths)->toBe([]);
});

it('takes a field back out again', function (): void {
    $paths = TranslatablePathCollector::collect([
        TextInput::make('heading')->translatable()->translatable(false),
    ], 'content');

    expect($paths)->toBe([]);
});

it('reads through layout components without adding to the path', function (): void {
    $paths = TranslatablePathCollector::collect([
        Section::make('Anything')->schema([
            TextInput::make('heading')->translatable(),
        ]),
    ], 'content');

    expect($paths)->toBe(['content.heading']);
});

it('gives a repeater a wildcard', function (): void {
    $paths = TranslatablePathCollector::collect([
        Repeater::make('items')->schema([
            TextInput::make('title')->translatable(),
            TextInput::make('image'),
        ]),
    ]);

    expect($paths)->toBe(['items.*.title']);
});

it('gives a builder a wildcard and reads through its blocks', function (): void {
    $paths = TranslatablePathCollector::collect([
        Builder::make('blocks')->blocks([
            Block::make('hero')->schema([
                TextInput::make('heading')->translatable(),
                TextInput::make('image'),
            ]),
            Block::make('grid')->schema([
                Repeater::make('items')->schema([
                    TextInput::make('title')->translatable(),
                ]),
            ]),
        ]),
    ]);

    expect($paths)->toBe([
        'blocks.*.data.heading',
        'blocks.*.data.items.*.title',
    ]);
});

it('collects from block definitions given a prefix', function (): void {
    $paths = TranslatablePathCollector::collect([
        Block::make('hero')->schema([
            TextInput::make('heading')->translatable(),
        ]),
    ], 'blocks.*.data');

    expect($paths)->toBe(['blocks.*.data.heading']);
});

it('produces declarations the model understands', function (): void {
    $paths = TranslatablePaths::make(TranslatablePathCollector::collect([
        Builder::make('blocks')->blocks([
            Block::make('hero')->schema([
                TextInput::make('heading')->translatable(),
                TextInput::make('image'),
            ]),
        ]),
    ]));

    expect($paths->matches(['blocks', 0, 'data', 'heading']))->toBeTrue()
        ->and($paths->matches(['blocks', 0, 'data', 'image']))->toBeFalse()
        ->and($paths->structuredColumns())->toBe(['blocks']);
});
