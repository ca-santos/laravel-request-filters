<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Illuminate\Database\Eloquent\Model;

interface ModelCriteriaContract
{

    function filterable(): array;

    function orderable(): array;

    function selectable(): array;

    function relatable(): array;

}
