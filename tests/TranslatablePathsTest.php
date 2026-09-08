<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;
use Tonsoo\FilamentTranslatable\Resources\Concerns\HasTranslatableLocaleSwitcher;
use Tonsoo\FilamentTranslatable\Resources\Pages\Concerns\InteractsWithTranslatableResourceData;

it('translates only the place a path names', function (): void {
    app()->setLocale('en');

    $data = (new FakeArticlePage())->save([
        'title' => 'A page',
        'content' => [
            'field' => ['input' => 'Written here'],
            'notes' => ['input' => 'Not written here'],
        ],
    ]);

    expect($data['title'])->toBe(['en' => 'A page'])
        ->and($data['content']['field']['input'])->toBe(['en' => 'Written here'])
        ->and($data['content']['notes']['input'])->toBe('Not written here');
});

it('translates every item a wildcard path reaches', function (): void {
    app()->setLocale('en');

    $data = (new FakeArticlePage())->save([
        'content' => [
            'items' => [
                ['heading' => 'First', 'image' => 'a.jpg'],
                ['heading' => 'Second', 'image' => 'b.jpg'],
            ],
        ],
    ]);

    expect($data['content']['items'][0]['heading'])->toBe(['en' => 'First'])
        ->and($data['content']['items'][1]['heading'])->toBe(['en' => 'Second'])
        ->and($data['content']['items'][0]['image'])->toBe('a.jpg');
});

it('stops matching a name by accident inside a column that names its own places', function (): void {
    app()->setLocale('en');

    $data = (new FakeArticlePage())->save([
        'title' => 'A page',
        'content' => [
            'items' => [
                ['heading' => 'First', 'title' => 'A card'],
            ],
        ],
    ]);

    expect($data['title'])->toBe(['en' => 'A page'])
        ->and($data['content']['items'][0]['title'])->toBe('A card');
});

it('leaves an excluded column alone however its keys are named', function (): void {
    app()->setLocale('en');

    $blocks = [
        ['type' => 'grid', 'data' => ['title' => 'A card', 'items' => [['title' => 'Another']]]],
    ];

    $data = (new FakeArticlePage())->save([
        'title' => 'A page',
        'blocks' => $blocks,
    ]);

    expect($data['blocks'])->toBe($blocks);
});

it('reads one language out of a path on fill', function (): void {
    app()->setLocale('es');

    $data = (new FakeArticlePage('es'))->fill([
        'content' => [
            'field' => ['input' => ['en' => 'Written here', 'es' => 'Escrito aqui']],
        ],
    ]);

    expect($data['content']['field']['input'])->toBe('Escrito aqui');
});

it('keeps the languages nobody is editing at a path', function (): void {
    app()->setLocale('es');

    $record = new FakeArticleModel();
    $record->setRawAttributes([
        'content' => json_encode([
            'field' => ['input' => ['en' => 'Written here', 'es' => 'Escrito aqui']],
        ]),
    ], true);

    $data = (new FakeArticlePage('es'))->save([
        'content' => ['field' => ['input' => 'Reescrito']],
    ], $record);

    expect($data['content']['field']['input'])->toBe([
        'en' => 'Written here',
        'es' => 'Reescrito',
    ]);
});

it('still matches a bare name at any depth outside a structured column', function (): void {
    app()->setLocale('en');

    $data = (new FakeArticlePage())->save([
        'sections' => [
            ['title' => 'A related row'],
        ],
    ]);

    expect($data['sections'][0]['title'])->toBe(['en' => 'A related row']);
});

it('reads a structured column with its translations resolved', function (): void {
    app()->setLocale('es');

    $article = new FakeArticleModel();
    $article->setRawAttributes([
        'content' => json_encode([
            'field' => ['input' => ['en' => 'Written here', 'es' => 'Escrito aqui']],
            'items' => [['heading' => ['en' => 'First'], 'image' => 'a.jpg']],
        ]),
    ], true);

    expect($article->content['field']['input'])->toBe('Escrito aqui')
        ->and($article->content['items'][0]['heading'])->toBe('First')
        ->and($article->content['items'][0]['image'])->toBe('a.jpg');
});

it('writes a structured column without losing the other languages', function (): void {
    app()->setLocale('es');

    $article = new FakeArticleModel();
    $article->setRawAttributes([
        'content' => json_encode([
            'field' => ['input' => ['en' => 'Written here']],
        ]),
    ], true);

    $article->content = ['field' => ['input' => 'Escrito aqui']];

    expect($article->getTranslations('content.field.input'))->toBe([
        'en' => 'Written here',
        'es' => 'Escrito aqui',
    ]);
});

it('sets and gets a translation at a path', function (): void {
    $article = new FakeArticleModel();

    $article->setTranslation('content.field.input', 'en', 'Written here');
    $article->setTranslation('content.field.input', 'es', 'Escrito aqui');

    expect($article->getTranslation('content.field.input', 'es'))->toBe('Escrito aqui')
        ->and($article->getTranslations('content.field.input'))->toBe([
            'en' => 'Written here',
            'es' => 'Escrito aqui',
        ]);
});

it('refuses to address more than one place at once', function (): void {
    $article = new FakeArticleModel();

    expect(fn (): FakeArticleModel => $article->setTranslation('content.items.*.heading', 'en', 'First'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws for a path nobody declared', function (): void {
    $article = new FakeArticleModel();

    expect(fn (): FakeArticleModel => $article->setTranslation('content.field.missing', 'en', 'x'))
        ->toThrow(InvalidArgumentException::class);
});

it('never turns a declared path into a column a query can select', function (): void {
    app()->setLocale('es');

    $column = FakeArticleModel::query()->where('title', 'A page')->toSql();
    $structure = FakeArticleModel::query()->where('content', 'anything')->toSql();

    expect($column)->toContain('json_extract')
        ->and($column)->toContain('es')
        ->and($structure)->not->toContain('json_extract');
});

class FakeArticlePage
{
    use InteractsWithTranslatableResourceData;

    public function __construct(?string $locale = 'en')
    {
        $this->translatableLocale = $locale;
    }

    public static function getResource(): string
    {
        return FakeArticleResource::class;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(array $data, ?Model $record = null): array
    {
        return $this->mutateTranslatableDataBeforePersist($data, $record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function fill(array $data): array
    {
        return $this->mutateFormDataBeforeFill($data);
    }
}

class FakeArticleResource
{
    use HasTranslatableLocaleSwitcher;

    public static function getModel(): string
    {
        return FakeArticleModel::class;
    }

    /**
     * @return array<int, string>
     */
    public static function getTranslatableLocales(): array
    {
        return ['en', 'es'];
    }
}

class FakeArticleModel extends Model
{
    use HasTranslations;

    protected $table = 'articles';

    protected $guarded = [];

    protected array $translatable = [
        'title',
        'content.field.input',
        'content.items.*.heading',
    ];

    protected array $translatableExcept = [
        'blocks',
    ];
}
