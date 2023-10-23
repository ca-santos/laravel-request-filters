<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use \Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @mixin Builder
 */
class BaseCriteria extends Builder
{

    protected Builder $builder;
    protected Request|Collection $request;
    protected ModelCriteriaContract $criteriaConfig;

    protected array $defaultCriteria = [
        'filter'
    ];

    /**
     * @param Builder $model
     * @param Request $request
     * @param $modelCriteria
     */
    public function __construct(Builder $model, Request|Collection $request, $modelCriteria)
    {
        parent::__construct($model->getQuery());
        $this->builder = $model;
        $this->request = $request;
        $this->criteriaConfig = new $modelCriteria();
    }

    /**
     * @param array $filters
     * @param string $type
     * @return bool
     * @throws Exception
     */
    protected function checkFields(array $filters, string $type): bool
    {

        $filterType = $this->criteriaConfig->{$type}();

        if (isset($filterType[0]) && $filterType[0] === '*') {
            return true;
        }

        if (array_diff($filters, $filterType)) {
            throw new Exception('Not allowed filters: ' . implode(', ', array_diff($filters, $filterType)));
        }

        return true;
    }

    protected function clearFields(array $filters, string $type): array
    {

        $filtersDefined = $this->criteriaConfig->{$type}();

        if (isset($filtersDefined[0]) && $filtersDefined[0] === '*') {
            return $filters;
        }

        $allowed = [];
        foreach ($filters as $key => $filter) {
            if (!in_array('!' . $key, $filtersDefined) && in_array($key, $filtersDefined)) {
                $allowed[$key] = $filter;
            }
        }

        return $allowed;

    }

}
