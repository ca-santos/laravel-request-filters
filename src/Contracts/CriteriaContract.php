<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * One stage of the pipeline (filter, order, select, ...): takes the builder
 * it was constructed with and returns it mutated.
 */
interface CriteriaContract
{
    public function apply(): Builder;
}
