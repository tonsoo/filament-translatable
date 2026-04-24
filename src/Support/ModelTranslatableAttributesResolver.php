<?php

declare(strict_types=1);

namespace Tonsoo\FilamentTranslatable\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Throwable;

final class ModelTranslatableAttributesResolver
{
    public function resolve(mixed $model): array
    {
        if (! $model instanceof Model) {
            return [];
        }

        $attributes = $this->getModelAttributes($model);
        if (! is_array($attributes)) {
            return [];
        }

        return array_values(array_unique(array_filter($attributes)));
    }

    private function getModelAttributes(Model $model): mixed
    {
        if (method_exists($model, 'getTranslatableAttributes')) {
            return $model->getTranslatableAttributes();
        }

        try {
            $defaults = (new ReflectionClass($model))->getDefaultProperties();
            return $defaults['translatable'] ?? [];
        } catch (Throwable) {
            return [];
        }
    }
}
