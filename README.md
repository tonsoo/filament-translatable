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

### Translating one place inside a column

A name in `$translatable` is a column that holds a map of languages, and it is
also matched **by name wherever it appears** in a form payload — which is what
makes a repeater of rows carrying the same field work with no configuration.

That is a guess, and it is the wrong one as soon as a JSON column holds a
structure with a field that happens to share a name. Say where the translation
actually is instead:

```php
class Page extends Model
{
    use HasTranslations;

    protected array $translatable = [
        'title',                    // the column is a map of languages
        'content.field.input',      // one place inside the `content` column is
        'content.items.*.heading',  // `*` is any key at that depth - a repeater
    ];
}
```

Declaring a path makes that column **structured**: inside it, only the declared
places are translated, and a name matched by accident no longer is. Everything
else in the column is left exactly as it was written.

`$page->content` comes back with its own shape, with those places resolved to
the language being read (falling back the same way a column does), so a template
gets sentences where the sentences are. Writing keeps the languages nobody is
editing:

```php
app()->setLocale('es');

$page->content = ['field' => ['input' => 'Escrito aqui']];
$page->getTranslations('content.field.input');
// ['en' => 'Written here', 'es' => 'Escrito aqui']

$page->setTranslation('content.items.0.heading', 'es', 'Primero');
$page->getTranslation('content.items.0.heading', 'es');
```

`setTranslation()` and `getTranslations()` address one value, so the path they
are given may not contain a wildcard.

### Marking the fields instead of writing the paths

Paths on the model track the shape of a form, and the two drift. Mark the fields
instead and let the model ask what was marked:

```php
use Filament\Forms\Components\TextInput;
use Tonsoo\FilamentTranslatable\Support\TranslatablePathCollector;

TextInput::make('heading')->translatable(),   // ->translatable(false) takes it back
```

```php
class Page extends Model
{
    use HasTranslations;

    public function getTranslatableAttributes(): array
    {
        return ['title', ...TranslatablePathCollector::collect([PageBuilder::blocks()])];
    }
}
```

The collector walks the schema **definition**, so a repeater or a builder becomes
the wildcard the stored data needs:

```php
TranslatablePathCollector::collect([
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

// ['blocks.*.data.heading', 'blocks.*.data.items.*.title']
```

Pass a prefix to collect from block definitions on their own:
`TranslatablePathCollector::collect($blocks, 'blocks.*.data')`.

The declarations are read once per model class per process, so overriding
`getTranslatableAttributes()` costs one walk however many records are loaded.

**The model still has to be the one that answers.** A form only exists in the
panel, while the site rendering a page, an API serialising it and a queued job
reading it all go through the model — and a value stored as
`{"en": …, "pt_BR": …}` is unreadable to anything that does not know it is a
translation. Marking a field is a way of writing the model's list without
repeating yourself, not a second place for the answer to live.

### Columns that hold no translations at all

A page builder's sections are structure from top to bottom. Name the column and
nothing inside it is translated, or even looked at:

```php
protected array $translatableExcept = ['blocks'];
```

Without this, a page whose own `title` is translatable and whose builder has a
card with a field called `title` stores that card's title as
`{"en": "A card"}` — which the panel hides from you, because it resolves the map
back to a string every time the form is opened, and which the template printing
it does not.

Two things worth knowing:

- **Order matters for wildcards.** The languages nobody is editing are carried
  over by position, because a builder or repeater dehydrates to a list and the
  key that identified an item while the form was open is gone by the time the
  payload is saved. Reordering items and saving in a second language moves the
  first language's text with the position, not with the item.
- **A declared path is not a column.** Query mapping (`where('title', …)` →
  `title->{locale}`) applies to the columns only; nothing tries to select
  `content.field.input->es`.

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

// Paths, for a translation that lives inside a column
$post->setTranslation('content.field.input', 'es', 'Escrito aqui');
$post->getTranslation('content.field.input', 'es');
$post->getTranslations('content.field.input');
```

Dot notation on the model itself is read as *attribute and locale*
(`title.es`), so a path is passed to the methods above rather than to the
property.

## Locale-Aware Query Columns

When querying translatable attributes, the package maps plain translatable column names to the current locale JSON path.

```php
app()->setLocale('pt_BR');

Post::query()->where('title', '=', 'Teste');
// same intent as querying: title->pt_BR = 'Teste'
```

You can also pass explicit locale dot notation:

```php
Post::query()->where('title.pt_BR', '=', 'Teste');
```

Disable this behavior per query:

```php
Post::query()
    ->withoutAutoTranslationsQuery()
    ->where('title->en', '=', 'Hello');
```

Re-enable later in the same chain:

```php
Post::query()
    ->where('title', 'Teste')
    ->withoutAutoTranslationsQuery()
    ->where('title->en', 'Hello')
    ->withAutoTranslationsQuery()
    ->where('title', 'Teste');
```
