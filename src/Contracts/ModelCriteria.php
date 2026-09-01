<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Contracts;

/**
 * The whitelist every Eloquent model exposes to the filtering engine: which
 * fields may be filtered, ordered, selected, or traversed through a relation.
 *
 * `['*']` as the sole element of any of these arrays means "everything is
 * allowed" (this is what {@see \CaueSantos\LaravelRequestFilters\Criteria\DefaultCriteria}
 * does). Otherwise every requested field is checked against the list.
 *
 * This is intentionally the same 4-method shape the package has always
 * exposed (`CaueSantos\LaravelRequestFilters\Criteria\ModelCriteriaContract`),
 * moved here as the canonical definition; the old interface now simply
 * extends this one so existing implementations keep working unmodified.
 */
interface ModelCriteria
{
    /** @return list<string> */
    public function filterable(): array;

    /** @return list<string> */
    public function orderable(): array;

    /** @return list<string> */
    public function selectable(): array;

    /** @return list<string> */
    public function relatable(): array;
}
