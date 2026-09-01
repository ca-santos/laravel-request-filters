<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Contracts;

/**
 * Optional additive capability on top of {@see ExtendedModelCriteria}:
 * declares which fields (plain columns, computed fields, or dotted relation
 * paths) the `q` full-text search request parameter is allowed to search
 * across. The engine checks `instanceof SearchableModelCriteria` before ever
 * applying `q` - a criteria class that doesn't implement this simply has no
 * search capability, the same way a plain {@see ModelCriteria} implementation
 * has none of {@see ExtendedModelCriteria}'s capabilities either.
 */
interface SearchableModelCriteria extends ExtendedModelCriteria
{
    /** @return list<string> fields `q` may search across - an empty list means the parameter has no effect */
    public function searchable(): array;
}
