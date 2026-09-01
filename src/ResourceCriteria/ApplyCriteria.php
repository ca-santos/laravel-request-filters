<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\Resources\BaseResource;
use CaueSantos\Brik\Http\Requests\BrikFormRequest;
use Illuminate\Database\Eloquent\Builder;

/** @deprecated Legacy Brik-coupled orchestrator, kept only for backward compatibility. See {@see ResourceFilters}. */
class ApplyCriteria
{
    public static function applyCriteria(Builder $builder, BaseResource $resource, BrikFormRequest $request): Builder
    {

        $query = $request->all();

        if (isset($query['filters'])) {
            $builder = (new ComplexFilterCriteriaB($builder, $resource, $request))->apply();
        }

        if (isset($query['count'])) {
            $builder = (new CountCriteria($builder, $resource, $request))->apply();
        }

        if (isset($query['order'])) {
            $builder = (new OrderByCriteria($builder, $resource, $request))->apply();
        }

        return $builder;

    }
}
