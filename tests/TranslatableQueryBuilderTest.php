<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Tonsoo\FilamentTranslatable\Database\Eloquent\TranslatableBuilder;
use Tonsoo\FilamentTranslatable\Concerns\HasTranslations;

it('maps translatable where columns to current locale JSON selector', function (): void {
    app()->setLocale('pt_BR');

    $builder = makeBuilder(new QueryPost());
    $builder->where('title', '=', 'Teste');

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title->pt_BR');
});

it('accepts explicit locale path in dot notation for where clauses', function (): void {
    app()->setLocale('en');

    $builder = makeBuilder(new QueryPost());
    $builder->where('title.pt_BR', '=', 'Teste');

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title->pt_BR');
});

it('can disable locale-aware column mapping per query', function (): void {
    app()->setLocale('pt_BR');

    $builder = makeBuilder(new QueryPost());
    $builder->withoutAutoTranslationsQuery()->where('title', '=', 'Teste');

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title');
});

it('maps whereIn columns forwarded through query builder passthrough', function (): void {
    app()->setLocale('pt_BR');

    $builder = makeBuilder(new QueryPost());
    $builder->whereIn('title', ['Teste']);

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title->pt_BR');
});

it('supports toggling auto-translations query mapping with alias methods', function (): void {
    app()->setLocale('pt_BR');

    $builder = makeBuilder(new QueryPost());

    $builder->where('title', 'Teste')
        ->withoutAutoTranslationsQuery()
        ->where('title->en', 'Hello')
        ->where('title', 'Teste')
        ->withAutoTranslationsQuery()
        ->where('title', 'Teste');

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title->pt_BR');
    expect($builder->getQuery()->wheres[1]['column'])->toBe('title->en');
    expect($builder->getQuery()->wheres[2]['column'])->toBe('title');
    expect($builder->getQuery()->wheres[3]['column'])->toBe('title->pt_BR');
});

it('applies MySQL collation for case-insensitive whereLike on translatable JSON selectors', function (): void {
    app()->setLocale('pt_BR');

    [$builder, $grammar] = makeMysqlBuilder(new QueryPost(), 'utf8mb4_unicode_ci');
    $builder->whereLike('title', 'esmal%');

    $column = $builder->getQuery()->wheres[0]['column'];

    expect($column)->toBeInstanceOf(Expression::class);
    expect($column->getValue($grammar))->toBe("wrapped(title->pt_BR) collate utf8mb4_unicode_ci");
});

it('does not apply collation for case-sensitive whereLike on translatable JSON selectors', function (): void {
    app()->setLocale('pt_BR');

    [$builder] = makeMysqlBuilder(new QueryPost(), 'utf8mb4_unicode_ci');
    $builder->whereLike('title', 'esmal%', true);

    expect($builder->getQuery()->wheres[0]['column'])->toBe('title->pt_BR');
});

it('applies MySQL collation for where like operator on translatable JSON selectors', function (): void {
    app()->setLocale('pt_BR');

    [$builder, $grammar] = makeMysqlBuilder(new QueryPost(), 'utf8mb4_unicode_ci');
    $builder->where('title', 'like', 'esmal%');

    $column = $builder->getQuery()->wheres[0]['column'];

    expect($column)->toBeInstanceOf(Expression::class);
    expect($column->getValue($grammar))->toBe("wrapped(title->pt_BR) collate utf8mb4_unicode_ci");
});

class QueryPost extends Model
{
    use HasTranslations;

    public $timestamps = false;

    protected $table = 'query_posts';

    protected $guarded = [];

    protected array $translatable = ['title'];
}

function makeBuilder(Model $model): TranslatableBuilder
{
    $grammar = \Mockery::mock(Grammar::class);
    $grammar->shouldReceive('getOperators')->andReturn([]);
    $grammar->shouldReceive('getBitwiseOperators')->andReturn([]);
    $processor = \Mockery::mock(Processor::class);
    $connection = \Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $query = new BaseQueryBuilder($connection);

    return tap(new TranslatableBuilder($query), function (TranslatableBuilder $builder) use ($model): void {
        $builder->setModel($model);
    });
}

/**
 * @return array{0: TranslatableBuilder, 1: Grammar}
 */
function makeMysqlBuilder(Model $model, ?string $collation = 'utf8mb4_unicode_ci'): array
{
    $grammar = \Mockery::mock(Grammar::class);
    $grammar->shouldReceive('getOperators')->andReturn([]);
    $grammar->shouldReceive('getBitwiseOperators')->andReturn([]);
    $grammar->shouldReceive('wrap')
        ->withArgs(static fn (string $column): bool => str_contains($column, '->'))
        ->andReturnUsing(static fn (string $column): string => "wrapped({$column})");
    $grammar->shouldReceive('getValue')
        ->withArgs(static fn (mixed $value): bool => $value instanceof Expression)
        ->andReturnUsing(static fn (Expression $value) => $value->getValue($grammar));

    $processor = \Mockery::mock(Processor::class);
    $connection = \Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getConfig')->with('collation')->andReturn($collation);

    $query = new BaseQueryBuilder($connection);

    $builder = tap(new TranslatableBuilder($query), function (TranslatableBuilder $builder) use ($model): void {
        $builder->setModel($model);
    });

    return [$builder, $grammar];
}
