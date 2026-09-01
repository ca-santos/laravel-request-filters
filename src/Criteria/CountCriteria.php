<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\CriteriaContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * `count=relation1,relation2` annotates the query with `withCount()` for each
 * named relation (e.g. `?count=tasks,comments` adds `tasks_count` and
 * `comments_count` to every result). Each relation is still checked against
 * `relatable()`.
 *
 * This used to be an unimplemented no-op stub (the request parameter was
 * accepted but had no effect); this is a new, additive capability rather than
 * a change to previously-working behaviour.
 */
class CountCriteria extends BaseCriteria implements CriteriaContract
{
    public function apply(): Builder
    {
        $relations = array_values(array_filter(explode(',', (string) $this->request->get('count', ''))));

        $allowed = array_values(array_filter(
            $relations,
            fn ($relation) => self::isFieldAllowed($relation, $this->criteriaConfig->relatable())
        ));

        if (!empty($allowed)) {
            $this->builder = $this->builder->withCount($allowed);
        }

        return $this->builder;
    }
}
