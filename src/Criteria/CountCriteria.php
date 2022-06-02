<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Illuminate\Database\Eloquent\Builder;

class CountCriteria extends BaseCriteria implements CriteriaContract
{

    function apply(): Builder
    {
        return $this->builder;
//        $counts = $this->request->get('count', []);
//        return $this->builder->count(explode(',', $counts));
    }

}
