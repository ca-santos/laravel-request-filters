<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use CaueSantos\LaravelRequestFilters\Contracts\FiscalYearResolver;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Single source of truth for every "date shortcut" (today, this week, last
 * month, this financial year, ...). Both the SQL side
 * ({@see \CaueSantos\LaravelRequestFilters\Criteria\FilterableByDates} and the
 * generic filter operator dispatch) and the in-memory evaluator
 * ({@see \CaueSantos\LaravelRequestFilters\Criteria\ComplexFilterCriteriaToCode})
 * resolve ranges through this class, so the same shortcut always means the
 * same range regardless of which engine evaluates it.
 *
 * Every range is half-open `[from, to)` - `to` is the first excluded instant -
 * except the three "rolling window" day shortcuts (`last_n_days`,
 * `next_n_days`, `n_days_ago`), which are closed ranges anchored to the exact
 * current instant (`now`), matching their original, intentionally different
 * semantics (a trailing/leading window "as of right now", not a calendar
 * period boundary).
 */
final class DateShortcuts
{
    /** @return list<string> every shortcut key this package understands */
    public static function keys(): array
    {
        return [
            'today', 'yesterday', 'tomorrow',
            'this_week', 'last_week', 'next_week', 'last_n_weeks', 'next_n_weeks', 'n_weeks_ago',
            'this_month', 'last_month', 'next_month', 'last_n_months', 'next_n_months', 'n_months_ago',
            'last_n_days', 'next_n_days', 'n_days_ago',
            'this_quarter', 'last_quarter', 'next_quarter', 'last_n_quarters', 'next_n_quarters', 'n_quarters_ago',
            'this_year', 'last_year', 'next_year', 'last_n_years', 'next_n_years', 'n_years_ago',
            'this_financial_year', 'last_financial_year', 'next_financial_year',
        ];
    }

    public static function isFiscalYear(string $key): bool
    {
        return str_ends_with($key, 'financial_year');
    }

    /** Whether this shortcut requires an integer `$n` argument. */
    public static function requiresN(string $key): bool
    {
        return (bool) preg_match('/^(last_n_|next_n_|n_.*_ago$)/', $key)
            && !self::isFiscalYear($key);
    }

    public static function range(string $key, ?int $n = null, ?FiscalYearResolver $fiscalYearResolver = null): DateRange
    {
        if (self::isFiscalYear($key)) {
            $resolver = $fiscalYearResolver ?? app(FiscalYearResolver::class);
            $range = $resolver->resolve($key);

            return new DateRange($range['from'], $range['to']);
        }

        $now = Carbon::now();
        $n = $n ?? 0;

        return match ($key) {
            'today' => new DateRange(Carbon::today(), Carbon::tomorrow()),
            'yesterday' => new DateRange(Carbon::yesterday(), Carbon::today()),
            'tomorrow' => new DateRange(Carbon::tomorrow(), Carbon::tomorrow()->addDay()),

            'this_week' => new DateRange($now->copy()->startOfWeek(), $now->copy()->startOfWeek()->addWeek()),
            'last_week' => new DateRange($now->copy()->startOfWeek()->subWeek(), $now->copy()->startOfWeek()),
            'next_week' => new DateRange($now->copy()->startOfWeek()->addWeek(), $now->copy()->startOfWeek()->addWeeks(2)),
            'last_n_weeks' => new DateRange($now->copy()->startOfWeek()->subWeeks($n), $now->copy()->startOfWeek()),
            'next_n_weeks' => new DateRange($now->copy()->startOfWeek()->addWeek(), $now->copy()->startOfWeek()->addWeeks($n + 1)),
            'n_weeks_ago' => new DateRange($now->copy()->startOfWeek()->subWeeks($n), $now->copy()->startOfWeek()->subWeeks($n - 1)),

            'this_month' => new DateRange($now->copy()->startOfMonth(), $now->copy()->startOfMonth()->addMonth()),
            'last_month' => new DateRange($now->copy()->startOfMonth()->subMonth(), $now->copy()->startOfMonth()),
            'next_month' => new DateRange($now->copy()->startOfMonth()->addMonth(), $now->copy()->startOfMonth()->addMonths(2)),
            'last_n_months' => new DateRange($now->copy()->startOfMonth()->subMonths($n), $now->copy()->startOfMonth()),
            'next_n_months' => new DateRange($now->copy()->startOfMonth()->addMonth(), $now->copy()->startOfMonth()->addMonths($n + 1)),
            'n_months_ago' => new DateRange($now->copy()->startOfMonth()->subMonths($n), $now->copy()->startOfMonth()->subMonths($n - 1)),

            // Rolling "as of right now" windows: closed range, deliberately not a calendar-period boundary.
            'last_n_days' => new DateRange($now->copy()->subDays($n)->startOfDay(), $now->copy()),
            'next_n_days' => new DateRange($now->copy()->startOfDay(), $now->copy()->addDays($n)->endOfDay()),
            'n_days_ago' => new DateRange($now->copy()->subDays($n)->startOfDay(), $now->copy()->subDays($n)->endOfDay()),

            'this_quarter' => new DateRange($now->copy()->firstOfQuarter(), $now->copy()->firstOfQuarter()->addQuarter()),
            'last_quarter' => new DateRange($now->copy()->firstOfQuarter()->subQuarter(), $now->copy()->firstOfQuarter()),
            'next_quarter' => new DateRange($now->copy()->firstOfQuarter()->addQuarter(), $now->copy()->firstOfQuarter()->addQuarters(2)),
            'last_n_quarters' => new DateRange($now->copy()->firstOfQuarter()->subQuarters($n), $now->copy()->firstOfQuarter()),
            'next_n_quarters' => new DateRange($now->copy()->firstOfQuarter()->addQuarter(), $now->copy()->firstOfQuarter()->addQuarters($n + 1)),
            'n_quarters_ago' => new DateRange($now->copy()->firstOfQuarter()->subQuarters($n), $now->copy()->firstOfQuarter()->subQuarters($n - 1)),

            'this_year' => new DateRange($now->copy()->startOfYear(), $now->copy()->startOfYear()->addYear()),
            'last_year' => new DateRange($now->copy()->startOfYear()->subYear(), $now->copy()->startOfYear()),
            'next_year' => new DateRange($now->copy()->startOfYear()->addYear(), $now->copy()->startOfYear()->addYears(2)),
            'n_years_ago' => new DateRange($now->copy()->startOfYear()->subYears($n), $now->copy()->startOfYear()->subYears($n - 1)),
            // NOTE: kept intentionally asymmetric with last_n_months/last_n_weeks (subYears($n + 1), not $n) -
            // this mirrors the pre-existing behaviour of the trait this replaces and is preserved verbatim
            // for backward compatibility rather than "corrected" as an unrequested behaviour change.
            'last_n_years' => new DateRange($now->copy()->startOfYear()->subYears($n + 1), $now->copy()->startOfYear()),
            'next_n_years' => new DateRange($now->copy()->startOfYear()->addYear(), $now->copy()->startOfYear()->addYears($n + 1)),

            default => throw new InvalidArgumentException("Unknown date shortcut [{$key}]."),
        };
    }

    /** Whether `$key`'s range is a closed interval (inclusive `to`) rather than half-open. */
    public static function isClosedRange(string $key): bool
    {
        return in_array($key, ['last_n_days', 'next_n_days', 'n_days_ago'], true);
    }

    public static function contains(string $key, Carbon $date, ?int $n = null, ?FiscalYearResolver $fiscalYearResolver = null): bool
    {
        $range = self::range($key, $n, $fiscalYearResolver);

        if (self::isClosedRange($key)) {
            return $date->greaterThanOrEqualTo($range->from) && $date->lessThanOrEqualTo($range->to);
        }

        return $range->contains($date);
    }
}
