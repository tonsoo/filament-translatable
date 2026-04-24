<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;

it('stores and reads translations for a translatable attribute', function (): void {
    $post = new TestPost();

    $post->setTranslation('title', 'en', 'Hello');
    $post->setTranslation('title', 'es', 'Hola');

    expect($post->getTranslation('title', 'en'))->toBe('Hello');
    expect($post->getTranslation('title', 'es'))->toBe('Hola');
});

it('falls back to configured locale when requested locale is empty', function (): void {
    $post = new TestPost();

    $post->setTranslation('title', 'en', 'Fallback title');

    expect($post->getTranslation('title', 'es'))->toBe('Fallback title');
});

it('throws when attribute is not marked translatable', function (): void {
    $post = new TestPost();

    expect(fn (): TestPost => $post->setTranslation('slug', 'en', 'test'))
        ->toThrow(InvalidArgumentException::class);
});

it('reads translatable attributes directly using current locale', function (): void {
    app()->setLocale('es');

    $post = new TestPost();
    $post->setTranslations('title', [
        'en' => 'Hello',
        'es' => 'Hola',
    ]);

    expect($post->title)->toBe('Hola');
});

it('writes translatable attributes directly using current locale', function (): void {
    app()->setLocale('es');

    $post = new TestPost();
    $post->title = 'Nuevo titulo';

    expect($post->getTranslations('title'))->toBe([
        'es' => 'Nuevo titulo',
    ]);
});

it('supports dot notation for locale-specific read/write', function (): void {
    $post = new TestPost();

    $post->{'title.es'} = 'Hola';
    $post->{'title.fr'} = 'Salut';

    expect($post->{'title.es'})->toBe('Hola');
    expect($post->{'title.fr'})->toBe('Salut');
});

it('accepts associative locale maps on direct translatable attribute', function (): void {
    $post = new TestPost();

    $post->title = ['en' => 'Hello', 'es' => 'Hola'];

    expect($post->getTranslations('title'))->toBe([
        'en' => 'Hello',
        'es' => 'Hola',
    ]);
});

it('rejects invalid non-associative translation payloads', function (): void {
    $post = new TestPost();

    expect(function () use ($post): void {
        $post->title = ['Hello', 'Ola'];
    })->toThrow(InvalidArgumentException::class);
});

class TestPost extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected array $translatable = ['title'];
}
