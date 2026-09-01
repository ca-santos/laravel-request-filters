<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Support\DateShortcuts;
use Illuminate\Database\Eloquent\Builder;

/**
 * Local scopes for every date shortcut (`Model::whereDateIsToday($column)`,
 * `Model::whereDateIsLastNMonths($column, 3)`, ...). Every scope delegates to
 * {@see DateShortcuts}, the single source of truth also used by the generic
 * `date_*` filter operators and the in-memory evaluator, so a shortcut always
 * means the same range everywhere it's used.
 */
trait FilterableByDates
{
    private function applyDateShortcut(Builder $query, string $column, string $key, ?int $n = null): Builder
    {
        $range = DateShortcuts::range($key, $n);
        $qualified = $query->qualifyColumn($column);

        return $query->where($qualified, '>=', $range->from)
            ->where($qualified, DateShortcuts::isClosedRange($key) ? '<=' : '<', $range->to);
    }

    public function scopeWhereDateIsToday(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'today');
    }

    public function scopeWhereDateIsYesterday(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'yesterday');
    }

    public function scopeWhereDateIsTomorrow(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'tomorrow');
    }

    public function scopeWhereDateIsThisWeek(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'this_week');
    }

    public function scopeWhereDateIsLastWeek(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_week');
    }

    public function scopeWhereDateIsNextWeek(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_week');
    }

    public function scopeWhereDateIsLastNWeeks(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_n_weeks', $n);
    }

    public function scopeWhereDateIsNextNWeeks(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_n_weeks', $n);
    }

    public function scopeWhereDateIsNWeeksAgo(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'n_weeks_ago', $n);
    }

    public function scopeWhereDateIsThisMonth(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'this_month');
    }

    public function scopeWhereDateIsLastMonth(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_month');
    }

    public function scopeWhereDateIsNextMonth(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_month');
    }

    public function scopeWhereDateIsLastNMonths(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_n_months', $n);
    }

    public function scopeWhereDateIsNextNMonths(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_n_months', $n);
    }

    public function scopeWhereDateIsNMonthsAgo(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'n_months_ago', $n);
    }

    public function scopeWhereDateIsLastNDays(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_n_days', $n);
    }

    public function scopeWhereDateIsNextNDays(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_n_days', $n);
    }

    public function scopeWhereDateIsNDaysAgo(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'n_days_ago', $n);
    }

    public function scopeWhereDateIsThisQuarter(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'this_quarter');
    }

    public function scopeWhereDateIsLastQuarter(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_quarter');
    }

    public function scopeWhereDateIsNextQuarter(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_quarter');
    }

    public function scopeWhereDateIsLastNQuarters(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_n_quarters', $n);
    }

    public function scopeWhereDateIsNextNQuarters(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_n_quarters', $n);
    }

    public function scopeWhereDateIsNQuartersAgo(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'n_quarters_ago', $n);
    }

    public function scopeWhereDateIsThisYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'this_year');
    }

    public function scopeWhereDateIsLastYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_year');
    }

    public function scopeWhereDateIsNextYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_year');
    }

    public function scopeWhereDateIsNYearsAgo(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'n_years_ago', $n);
    }

    public function scopeWhereDateIsLastNYears(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_n_years', $n);
    }

    public function scopeWhereDateIsNextNYears(Builder $query, string $column, int $n): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_n_years', $n);
    }

    public function scopeWhereDateIsThisFinancialYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'this_financial_year');
    }

    public function scopeWhereDateIsLastFinancialYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'last_financial_year');
    }

    public function scopeWhereDateIsNextFinancialYear(Builder $query, string $column): Builder
    {
        return $this->applyDateShortcut($query, $column, 'next_financial_year');
    }
}
