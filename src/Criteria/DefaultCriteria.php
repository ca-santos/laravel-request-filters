<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

class DefaultCriteria implements ModelCriteriaContract
{

    function filterable(): array
    {
        return ['*'];
    }

    function orderable(): array
    {
        return ['*'];
    }

    function selectable(): array
    {
        return ['*'];
    }

    function relatable(): array
    {
        return ['*'];
    }
}
