# Filament Translatable

Model and Filament page traits for runtime translations.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tonsoo/filament-translatable.svg)](https://packagist.org/packages/tonsoo/filament-translatable)
[![Tests](https://img.shields.io/github/actions/workflow/status/tonsoo/filesize/run-tests.yml?branch=main)](https://github.com/tonsoo/filament-translatable/actions)
[![License](https://img.shields.io/github/license/tonsoo/filesize.svg)](https://github.com/tonsoo/filament-translatable/blob/main/LICENSE)

---

Locale discovery uses `getTranslatableLocales()` or the static `$translatableLocales` property when present, then `app.available_locales` (or `app.locales`), and finally the current app locale.
Fallback translation uses `app.fallback_locale` when set.

## Supported Versions

- PHP: `^8.3`
- Laravel: `^13.0`
- Filament: `^5.0`
- Livewire: `^4.1`

## Installation

```bash
composer require tonsoo/filament-translatable
```

## Model Trait

```php
use Illuminate\Database\Eloquent\Model;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;

class Post extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'content'];
}
```

Translatable attributes must be JSON columns.

## Filament Page Trait

Use the page trait in your own create/edit pages:

```php
use Filament\Resources\Resource;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getTranslatableLocales(): array // optional
    {
        return (array) config('app.available_locales', []);
    }
}
```

`InteractsWithTranslatableResourceData` reads translatable attributes from the model.
Form inputs are scalar in the UI, and the trait converts translatable fields into locale maps for persistence.

## Optional Runtime Locale Switcher

Add this resource trait to render a locale select beside the page heading:

```php
use Tonsoo\FilamentTranslatable\Resources\Concerns\HasTranslatableLocaleSwitcher;

class ProductResource extends Resource
{
    use HasTranslatableLocaleSwitcher;

    protected static ?string $model = Product::class;

    public static function getTranslatableLocales(): array
    {
        return (array) config('app.available_locales', []);
    }

    protected static string $defaultTranslatableLocale = 'en'; // optional
    protected static array $translatableLocaleLabels = [ // optional
        'en' => 'English',
        'es' => 'Spanish',
    ];
}
```

You can also define the locale list with a static `protected array $translatableLocales = [...]` property instead of overriding `getTranslatableLocales()`.
The locale labels array is optional; if you omit it, the switcher uses the locale code as the label.
`protected static string $defaultTranslatableLocale = 'en';` is optional and is used only when it matches one of the configured locales.

```php
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Tonsoo\FilamentTranslatable\Resources\Pages\Concerns\InteractsWithTranslatableResourceData;

class CreateProduct extends CreateRecord
{
    use InteractsWithTranslatableResourceData;

    protected static string $resource = ProductResource::class;
}

class EditProduct extends EditRecord
{
    use InteractsWithTranslatableResourceData;

    protected static string $resource = ProductResource::class;
}
```

This automatically wires:
- `mutateFormDataBeforeFill()` on edit pages
- `mutateFormDataBeforeCreate()` on create pages
- `mutateFormDataBeforeSave()` on edit pages

## Model API

```php
$post->setTranslation('title', 'en', 'Hello');
$post->setTranslation('title', 'es', 'Hola');

$post->getTranslation('title', 'en');
$post->getTranslation('title', 'es'); // fallback uses app.fallback_locale
$post->getTranslations('title');

$post->title = 'Hello'; // current locale
$post->{'title.es'} = 'Hola'; // explicit locale
```
