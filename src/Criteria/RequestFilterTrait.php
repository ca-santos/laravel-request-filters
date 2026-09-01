<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria;

use CaueSantos\LaravelRequestFilters\Support\RelationIntrospector;
use CaueSantos\LaravelRequestFilters\Support\SchemaIntrospector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Add this trait (and implement {@see self::criteria()}) to any Eloquent
 * model to make it filterable through {@see ApplyCriteria}.
 */
trait RequestFilterTrait
{
    /**
     * @param  string|ModelCriteria  $modelCriteria
     *
     * @throws InvalidArgumentException
     */
    public static function applyCriteria(string|ModelCriteria $modelCriteria): Builder
    {
        return ApplyCriteria::applyCriteria($modelCriteria, static::query());
    }

    /** Relation-aware sort using the current request's `order` parameter and this model's own criteria. */
    public static function sort(): Builder
    {
        return ApplyCriteria::sort(static::query(), static::criteria());
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function getFilterDefs(): array
    {
        /** @var Model $model */
        $model = static::query()->getModel();

        $criteriaClass = self::criteria();

        if (is_string($criteriaClass) && !class_exists($criteriaClass)) {
            throw new InvalidArgumentException($criteriaClass.' is not a valid criteria class');
        }

        $modelCriteria = is_string($criteriaClass) ? new $criteriaClass : $criteriaClass;

        return [
            'model' => $model::class,
            'table' => $model->getTable(),
            'fillable' => [$model->getKeyName(), ...$model->getFillable()],
            'attributes' => $model->getAttributes(),
            'columns' => SchemaIntrospector::columns($model->getTable(), $model->getConnectionName()),
            'allowed' => [
                'filterable' => $modelCriteria->filterable(),
                'orderable' => $modelCriteria->orderable(),
                'selectable' => $modelCriteria->selectable(),
                'relatable' => $modelCriteria->relatable(),
            ],
            'relations' => collect(RelationIntrospector::discoverAll($model))
                ->map(fn ($info) => $info->relatedModel),
        ];
    }

    /** @return string|ModelCriteria a criteria class-string, or an already-configured instance */
    abstract public static function criteria(): string|ModelCriteria;
}
