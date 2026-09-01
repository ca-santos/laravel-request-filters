<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The pipeline orchestrator: given a model criteria and a builder, inspects
 * the current request for `complexFilters`, `filters`, `q`, `select`, `count`
 * and `order` and applies whichever stages are present, in that order.
 */
class ApplyCriteria
{
    /**
     * @param  string|ModelCriteria  $modelCriteria
     *
     * @throws InvalidArgumentException
     */
    public static function applyCriteria(
        string|ModelCriteria $modelCriteria,
        Builder $builder,
        bool $skipOrder = false,
        array|Collection $filters = []
    ): Builder {
        if (!in_array(ModelCriteria::class, class_implements($modelCriteria) ?: [], true)) {
            $name = is_object($modelCriteria) ? $modelCriteria::class : $modelCriteria;

            throw new InvalidArgumentException($name.' doesn\'t implement '.ModelCriteria::class);
        }

        $filters = collect($filters);

        $request = request();
        if ($filters->isNotEmpty()) {
            $request->query->add($filters->toArray());
        }

        $query = $request->query();

        if (isset($query['complexFilters'])) {
            $builder = (new ComplexFilterCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['filters'])) {
            $builder = (new FilterCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['q'])) {
            $builder = (new SearchCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['select'])) {
            $builder = (new SelectCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['count'])) {
            $builder = (new CountCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['order']) && !$skipOrder) {
            $builder = (new OrderByCriteria($builder, $request, $modelCriteria))->apply();
        }

        return $builder;
    }

    /** Relation-aware sort with a sensible default, using the current request's `order` parameter. */
    public static function sort(Builder $builder, string|ModelCriteria $modelCriteria = DefaultCriteria::class): Builder
    {
        return (new OrderByCriteria($builder, request(), $modelCriteria))->smartSort();
    }
}
