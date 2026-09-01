<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\SearchableModelCriteria;
use Illuminate\Database\Eloquent\Builder;

/**
 * Simple full-text search: `?q=term` matches any of the criteria's
 * `searchable()` fields (plain columns, computed fields, or dotted relation
 * paths) - a plain-column `contains` OR-ed together across every one of
 * them, reusing {@see BaseFilterCriteria::resolveAndApplyField()} so a
 * searchable field is resolved (and its value cast) exactly the same way a
 * `filters[field:contains]` condition on it already would be.
 *
 * A criteria class that doesn't implement {@see SearchableModelCriteria}, or
 * declares an empty `searchable()` list, is left untouched - `q` has no
 * effect unless a field has been explicitly opted in.
 */
class SearchCriteria extends BaseFilterCriteria
{
    public function apply(): Builder
    {
        $term = trim((string) $this->request->get('q', ''));
        $extended = $this->extendedCriteria();

        if ($term === '' || !$extended instanceof SearchableModelCriteria) {
            return $this->builder;
        }

        $columns = $extended->searchable();

        if ($columns === []) {
            return $this->builder;
        }

        $value = [$term];

        $this->builder->where(function (Builder $query) use ($columns, $value) {
            foreach ($columns as $column) {
                $this->resolveAndApplyField($query, $column, 'contains', null, $value, 'or');
            }
        });

        return $this->builder;
    }
}
