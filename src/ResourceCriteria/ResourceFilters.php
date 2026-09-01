<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\Resources\BaseResource;
use CaueSantos\Brik\Http\Requests\BrikFormRequest;
use CaueSantos\Support\IsMakeable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @deprecated Legacy Brik-coupled entry point, kept only for backward
 *             compatibility with existing call sites in `packages/brik` and
 *             `app/`. New code targeting a plain Eloquent model should use
 *             `CaueSantos\LaravelRequestFilters\Criteria\ApplyCriteria` instead.
 *
 * @method static static make(Builder $builder, BaseResource $resource, BrikFormRequest $request)
 */
class ResourceFilters
{
    use IsMakeable;

    private Builder $builder;

    private BaseResource $resource;

    private BrikFormRequest $request;

    public function __construct(Builder $builder, BaseResource $resource, BrikFormRequest $request)
    {
        $this->builder = $builder;
        $this->resource = $resource;
        $this->request = $request;
    }

    public function apply(): Builder
    {
        return ApplyCriteria::applyCriteria($this->builder, $this->resource, $this->request);
    }
}
