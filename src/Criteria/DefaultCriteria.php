<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ExtendedModelCriteria;

/**
 * The "no restrictions" criteria: every field is filterable/orderable/
 * selectable/relatable, and no computed fields/counters/custom behaviour is
 * declared. Used whenever a model doesn't provide its own criteria class.
 */
class DefaultCriteria implements ExtendedModelCriteria, ModelCriteriaContract
{
    public function filterable(): array
    {
        return ['*'];
    }

    public function orderable(): array
    {
        return ['*'];
    }

    public function selectable(): array
    {
        return ['*'];
    }

    public function relatable(): array
    {
        return ['*'];
    }

    public function computedFields(): array
    {
        return [];
    }

    public function counters(): array
    {
        return [];
    }

    public function customFilters(): array
    {
        return [];
    }

    public function customSorts(): array
    {
        return [];
    }

    public function aliases(): array
    {
        return [];
    }
}
