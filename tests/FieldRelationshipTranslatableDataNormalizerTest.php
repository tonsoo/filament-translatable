<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Illuminate\Database\Eloquent\Model;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;
use Tonsoo\FilamentTranslatable\Support\FieldRelationshipTranslatableDataNormalizer;

it('normalizes relationship data for fill in single locale mode', function (): void {
    app()->setLocale('en');

    $normalizer = new FieldRelationshipTranslatableDataNormalizer();

    $data = $normalizer->normalizeForFill([
        'name' => ['en' => 'Top Name', 'es' => 'Nombre'],
        'items' => [
            ['name' => ['en' => ['en' => 'Nested Name']]],
        ],
    ], ['name'], 'en');

    expect($data['name'])->toBe('Top Name')
        ->and($data['items'][0]['name'])->toBe('Nested Name');
});

it('normalizes relationship data for persist as locale maps', function (): void {
    app()->setLocale('en');

    $normalizer = new FieldRelationshipTranslatableDataNormalizer();

    $data = $normalizer->normalizeForPersist([
        'name' => 'Top Name',
        'items' => [
            ['name' => 'Nested Name'],
        ],
    ], ['name'], 'en');

    expect($data['name'])->toBe(['en' => 'Top Name'])
        ->and($data['items'][0]['name'])->toBe(['en' => 'Nested Name']);
});

it('preserves other locale translations when saving relationship data', function (): void {
    app()->setLocale('en');

    $normalizer = new FieldRelationshipTranslatableDataNormalizer();

    $record = new FakeRelatedModel();
    $record->setTranslations('name', [
        'en' => 'Old English',
        'es' => 'Viejo Espanol',
    ]);

    $data = $normalizer->normalizeForPersist([
        'name' => 'New English',
    ], ['name'], 'en', $record);

    expect($data['name'])->toBe([
        'en' => 'New English',
        'es' => 'Viejo Espanol',
    ]);
});

class FakeRelatedModel extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['name'];
}
