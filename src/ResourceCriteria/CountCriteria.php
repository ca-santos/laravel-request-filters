<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use Illuminate\Database\Eloquent\Builder;

class CountCriteria extends BaseCriteria implements CriteriaContract
{
    public function apply(): Builder
    {
        return $this->builder;
        //        $counts = $this->request->get('count', []);
        //        return $this->builder->count(explode(',', $counts));
    }
}
