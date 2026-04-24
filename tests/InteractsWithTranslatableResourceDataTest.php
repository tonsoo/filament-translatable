<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Illuminate\Database\Eloquent\Model;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;
use Tonsoo\FilamentTranslatable\Resources\Concerns\HasTranslatableLocaleSwitcher;
use Tonsoo\FilamentTranslatable\Resources\Pages\Concerns\InteractsWithTranslatableResourceData;

it('preserves other locale translations when saving single mode', function (): void {
    app()->setLocale('en');

    $page = new FakeEditPage();
    $page->translatableLocale = 'en';

    $record = new FakePageModel();
    $record->setTranslations('name', [
        'en' => 'Old English',
        'es' => 'Viejo Espanol',
    ]);

    $data = $page->mutateForSave([
        'name' => 'New English',
    ], $record);

    expect($data['name'])->toBe([
        'en' => 'New English',
        'es' => 'Viejo Espanol',
    ]);
});

it('normalizes single mode payload to locale maps when creating', function (): void {
    app()->setLocale('en');

    $page = new FakeEditPage();
    $page->translatableLocale = 'en';

    $data = $page->mutateForSave([
        'name' => [
            'en' => 'New English',
            'es' => 'Nuevo Espanol',
        ],
    ]);

    expect($data['name'])->toBe([
        'en' => 'New English',
        'es' => 'Nuevo Espanol',
    ]);
});

it('flattens nested translatable values recursively on fill in single mode', function (): void {
    app()->setLocale('en');

    $page = new FakeEditPage();
    $page->translatableLocale = 'en';

    $data = $page->mutateForFill([
        'name' => ['en' => 'Top Name', 'es' => 'Nombre'],
        'items' => [
            ['name' => ['en' => 'Nested Name', 'es' => 'Nombre Anidado']],
        ],
    ]);

    expect($data['name'])->toBe('Top Name')
        ->and($data['items'][0]['name'])->toBe('Nested Name');
});

it('normalizes nested translatable values recursively on save in single mode', function (): void {
    app()->setLocale('en');

    $page = new FakeEditPage();
    $page->translatableLocale = 'en';

    $data = $page->mutateForSave([
        'name' => 'Top Name',
        'items' => [
            ['name' => ['en' => ['en' => 'Nested Name']]],
        ],
    ]);

    expect($data['name'])->toBe([
        'en' => 'Top Name',
    ])
        ->and($data['items'][0]['name'])->toBe([
            'en' => 'Nested Name',
        ]);
});

class FakeEditPage
{
    use InteractsWithTranslatableResourceData;

    public static function getResource(): string
    {
        return FakeProductResource::class;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mutateForSave(array $data, ?Model $record = null): array
    {
        return $this->mutateTranslatableDataBeforePersist($data, $record);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mutateForFill(array $data): array
    {
        return $this->mutateFormDataBeforeFill($data);
    }
}

class FakeProductResource
{
    use HasTranslatableLocaleSwitcher;

    public static function getModel(): string
    {
        return FakePageModel::class;
    }

    /**
     * @return array<int, string>
     */
    public static function getTranslatableLocales(): array
    {
        return ['en', 'es'];
    }
}

class FakePageModel extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['name'];
}
