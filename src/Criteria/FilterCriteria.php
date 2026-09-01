<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Support\Values;
use Illuminate\Database\Eloquent\Builder;

/**
 * Simple, flat filters: `filters[column[:operator[:modifier]]]=value`.
 *
 * Fields not present in the criteria's `filterable()` whitelist are dropped
 * silently (a request asking for a disallowed field simply has no effect on
 * that field, rather than failing the whole request).
 */
class FilterCriteria extends BaseFilterCriteria
{
    public function apply(): Builder
    {
        foreach ((array) $this->request->get('filters', []) as $key => $rawValue) {
            $parts = explode(':', (string) $key);
            $field = $parts[0];
            $operatorKey = $parts[1] ?? 'eq';
            $modifier = $parts[2] ?? null;

            if (!self::isFieldAllowed($field, $this->criteriaConfig->filterable())) {
                continue;
            }

            $value = Values::convertValue(
                is_array($rawValue) ? $rawValue : explode(',', (string) $rawValue)
            );

            $this->resolveAndApplyField($this->builder, $field, $operatorKey, $modifier, $value);
        }

        return $this->builder;
    }
}
