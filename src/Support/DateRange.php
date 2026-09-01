<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Carbon\Carbon;

/**
 * A half-open date range `[from, to)`: `to` is the first instant excluded
 * from the range. Every date shortcut in the package is expressed this way
 * so a period never bleeds into the first instant of the next one (the
 * historical "+1 day" boundary bug this replaces).
 */
final class DateRange
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {
    }

    public function contains(Carbon $date): bool
    {
        return $date->greaterThanOrEqualTo($this->from) && $date->lessThan($this->to);
    }
}
