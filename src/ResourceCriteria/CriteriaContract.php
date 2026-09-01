<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use Illuminate\Database\Eloquent\Builder;

interface CriteriaContract
{
    public function apply(): Builder;
}
