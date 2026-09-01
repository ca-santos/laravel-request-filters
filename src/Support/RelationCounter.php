<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Closure;

/**
 * A field that represents "how many related records does this model have"
 * (optionally matching a sub-condition), e.g. `tasks_count` or
 * `completed_tasks`. The generic replacement for Brik's counter fields
 * (`HasManyCounter`, `BelongsToManyCounter`, the `*WithFilters` variants).
 *
 * Such a field is normally exposed as a `withCount()`/subquery SELECT alias,
 * which SQL does not allow referencing in a WHERE/HAVING clause. The engine
 * never compares the alias directly - it rewrites the comparison into a
 * `has($relation, $operator, $count, 'and', $constraint)` existence check,
 * re-applying `$constraint` (what is being counted) inside it.
 */
final class RelationCounter
{
    /** @param null|Closure(\Illuminate\Contracts\Database\Eloquent\Builder):void $constraint */
    public function __construct(
        public readonly string $name,
        public readonly string $relation,
        public readonly ?Closure $constraint = null,
    ) {
    }

    public static function make(string $name, string $relation, ?Closure $constraint = null): self
    {
        return new self($name, $relation, $constraint);
    }
}
