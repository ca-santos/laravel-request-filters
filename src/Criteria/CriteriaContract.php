<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Illuminate\Database\Eloquent\Builder;

interface CriteriaContract
{

    function apply(): Builder;

}
