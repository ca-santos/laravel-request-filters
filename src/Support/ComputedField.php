<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A field whose value is a SQL expression rather than a real column - e.g. a
 * concatenation (`CONCAT(first_name, ' ', last_name)`) or a formula. The
 * generic replacement for Brik's `ConcatenationField`/`FormulaField`.
 *
 * The engine never compares/orders by the field's alias (SQL does not allow
 * referencing a SELECT alias in WHERE), it always substitutes the resolved
 * expression instead.
 */
final class ComputedField
{
    /** @param string|Closure(Builder):string $expression */
    public function __construct(
        public readonly string $name,
        public readonly string|Closure $expression,
    ) {
    }

    public static function make(string $name, string|Closure $expression): self
    {
        return new self($name, $expression);
    }

    public function resolve(Builder $query): string
    {
        return $this->expression instanceof Closure
            ? ($this->expression)($query)
            : $this->expression;
    }
}
