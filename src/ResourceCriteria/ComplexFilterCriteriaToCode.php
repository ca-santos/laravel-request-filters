<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\Resources\BaseResource;
use App\Services\DateShortcutResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CaueSantos\Brik\Fields\RelationField;
use CaueSantos\Brik\Filters\DateFilterType;
use CaueSantos\Brik\Filters\FilterType;
use Illuminate\Support\Collection;

/**
 * @deprecated Legacy Brik-coupled in-memory evaluator, kept only for backward
 *             compatibility with `packages/brik`. See
 *             {@see \CaueSantos\LaravelRequestFilters\Criteria\ComplexFilterCriteriaToCode}
 *             for the generic, Eloquent-only equivalent.
 */
class ComplexFilterCriteriaToCode
{
    protected BaseResource $resource;

    private static function allowedOperators(): array
    {
        return array_keys(FilterType::all());
    }

    private static function allowedDateOperators(): array
    {
        return array_keys(DateFilterType::all());
    }

    public function __construct(BaseResource $resource)
    {
        $this->resource = $resource;
    }

    public function apply(Collection $collection, array $filterStructure): Collection
    {
        return $collection->filter(function ($item) use ($filterStructure) {
            return $this->evaluateFilterGroup($item, $filterStructure);
        })->values();
    }

    private function evaluateFilterGroup($item, array $group): bool
    {
        $logic = $group['logic'] ?? 'and';
        $filters = $group['filters'] ?? [];

        if (empty($filters)) {
            return true;
        }

        $results = array_map(function ($filter) use ($item) {
            return isset($filter['filters'])
              ? $this->evaluateFilterGroup($item, $filter)
              : $this->evaluateFilter($item, $filter);
        }, $filters);

        return $logic === 'and'
          ? !in_array(false, $results, true)
          : in_array(true, $results, true);
    }

    private function evaluateFilter($item, array $filter): bool
    {
        $fieldUniqueKey = $filter['field'] ?? $filter['attribute'];
        $attribute = $filter['attribute'];
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;

        $field = $this->resource->findFieldPathFromDotNotation(
            $fieldUniqueKey,
            ['uniqueKey', 'relationName', 'attribute']
        )[0] ?? null;

        $itemValue = $this->getAttributeValue($item, $attribute);

        if ($field && property_exists($field, 'transformValueForConditionalCallback') && is_callable($field->transformValueForConditionalCallback)) {
            $itemValue = call_user_func($field->transformValueForConditionalCallback, $item, $itemValue, $operator, $value);
        }

        return self::applyOperator($itemValue, $operator, $value);
    }

    private function getAttributeValue($item, string $attribute): mixed
    {
        $field = $this->resource->findFieldPathFromDotNotation(
            $attribute,
            ['uniqueKey', 'relationName', 'attribute']
        )[0] ?? null;

        if (!$field) {
            return data_get($item, $attribute);
        }

        $value = data_get(
            $item,
            $field->attribute,
            data_get($item, $field->relationName ?? '__NOT_REAL_ATTR__')
        );

        // For BelongsToMany/HasMany fields the value is a Collection of related models.
        // Pluck their primary keys so operators like 'in' can do a meaningful comparison.
        if ($value instanceof Collection && $field instanceof RelationField) {
            $keyName = $field->relatedResourceKeyName ?? 'id';

            return $value->pluck($keyName)->toArray();
        }

        return $value;
    }

    private static function tryParseDate(mixed $value): CarbonImmutable|Carbon|null
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (!is_string($value) && !$value instanceof \DateTimeInterface) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeForComparison(mixed $a, mixed $b): array
    {
        $dateA = self::tryParseDate($a);
        $dateB = self::tryParseDate($b);

        if ($dateA && $dateB) {
            return [$dateA->timestamp, $dateB->timestamp];
        }

        return [$a, $b];
    }

    private static function applyOperator(mixed $itemValue, string $operator, mixed $filterValue): bool
    {
        if (in_array($operator, self::allowedDateOperators(), true)) {
            return self::applyDateOperator($itemValue, $operator, $filterValue);
        }

        if (!in_array($operator, self::allowedOperators(), true)) {
            return false;
        }

        switch ($operator) {

            case 'eq':
            case '=':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a == $b;

            case '!eq':
            case '!=':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a != $b;

            case 'contains':
            case 'like':
                return stripos((string) $itemValue, (string) $filterValue) !== false;

            case '!contains':
            case 'not_contains':
            case '!like':
                return stripos((string) $itemValue, (string) $filterValue) === false;

            case 'starts':
            case 'starts_with':
                return stripos((string) $itemValue, (string) $filterValue) === 0;

            case '!starts':
            case 'not_starts':
            case 'not_starts_with':
                return stripos((string) $itemValue, (string) $filterValue) !== 0;

            case 'ends':
            case 'ends_with':
                return str_ends_with(strtolower((string) $itemValue), strtolower((string) $filterValue));

            case '!ends':
            case 'not_ends':
            case 'not_ends_with':
                return !str_ends_with(strtolower((string) $itemValue), strtolower((string) $filterValue));

            case 'empty':
                return empty($itemValue);

            case '!empty':
            case 'not_empty':
                return !empty($itemValue);

            case 'lt':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a < $b;

            case 'lte':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a <= $b;

            case 'gt':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a > $b;

            case 'gte':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $a >= $b;

            case 'between':
                [$min, $max] = (array) $filterValue;
                [$a, $min] = self::normalizeForComparison($itemValue, $min);
                [, $max] = self::normalizeForComparison($itemValue, $max);

                return $a >= $min && $a <= $max;

            case '!between':
            case 'not_between':
                [$min, $max] = (array) $filterValue;
                [$a, $min] = self::normalizeForComparison($itemValue, $min);
                [, $max] = self::normalizeForComparison($itemValue, $max);

                return $a < $min || $a > $max;

            case 'in':
                if (is_array($itemValue)) {
                    return !empty(array_intersect($itemValue, (array) $filterValue));
                }

                return in_array($itemValue, (array) $filterValue);

            case '!in':
            case 'not_in':
                if (is_array($itemValue)) {
                    return empty(array_intersect($itemValue, (array) $filterValue));
                }

                return !in_array($itemValue, (array) $filterValue);

            case 'is_null':
                return is_null($itemValue);

            case 'is_not_null':
                return !is_null($itemValue);

            default:
                return false;
        }
    }

    private static function applyDateOperator(mixed $itemValue, string $operator, mixed $n): bool
    {
        if (empty($itemValue)) {
            return false;
        }

        try {
            $date = Carbon::parse($itemValue)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $today = Carbon::today();
        $n = (int) $n;

        return match ($operator) {

            'date_yesterday' => $date->isSameDay($today->copy()->subDay()),
            'date_today' => $date->isSameDay($today),
            'date_tomorrow' => $date->isSameDay($today->copy()->addDay()),
            'date_last_n_days' => $date->between($today->copy()->subDays($n)->startOfDay(), $today->copy()->endOfDay()),
            'date_next_n_days' => $date->between($today->copy()->startOfDay(), $today->copy()->addDays($n)->endOfDay()),
            'date_n_days_ago' => $date->isSameDay($today->copy()->subDays($n)),

            'date_last_week' => $date->between($today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()),
            'date_this_week' => $date->between($today->copy()->startOfWeek(), $today->copy()->endOfWeek()),
            'date_next_week' => $date->between($today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek()),
            'date_last_n_weeks' => $date->between($today->copy()->subWeeks($n)->startOfWeek(), $today->copy()->endOfDay()),
            'date_next_n_weeks' => $date->between($today->copy()->startOfDay(), $today->copy()->addWeeks($n)->endOfWeek()),
            'date_n_weeks_ago' => $date->between($today->copy()->subWeeks($n)->startOfWeek(), $today->copy()->subWeeks($n)->endOfWeek()),

            'date_last_month' => $date->between($today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()),
            'date_this_month' => $date->between($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'date_next_month' => $date->between($today->copy()->addMonth()->startOfMonth(), $today->copy()->addMonth()->endOfMonth()),
            'date_last_n_months' => $date->between($today->copy()->subMonths($n)->startOfMonth(), $today->copy()->endOfDay()),
            'date_next_n_months' => $date->between($today->copy()->startOfDay(), $today->copy()->addMonths($n)->endOfMonth()),
            'date_n_months_ago' => $date->between($today->copy()->subMonths($n)->startOfMonth(), $today->copy()->subMonths($n)->endOfMonth()),

            'date_last_quarter' => $date->between($today->copy()->subQuarter()->firstOfQuarter(), $today->copy()->subQuarter()->lastOfQuarter()),
            'date_this_quarter' => $date->between($today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter()),
            'date_next_quarter' => $date->between($today->copy()->addQuarter()->firstOfQuarter(), $today->copy()->addQuarter()->lastOfQuarter()),
            'date_last_n_quarters' => $date->between($today->copy()->subQuarters($n)->firstOfQuarter(), $today->copy()->endOfDay()),
            'date_next_n_quarters' => $date->between($today->copy()->startOfDay(), $today->copy()->addQuarters($n)->lastOfQuarter()),
            'date_n_quarters_ago' => $date->between($today->copy()->subQuarters($n)->firstOfQuarter(), $today->copy()->subQuarters($n)->lastOfQuarter()),

            'date_last_year' => $date->between($today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()),
            'date_this_year' => $date->between($today->copy()->startOfYear(), $today->copy()->endOfYear()),
            'date_next_year' => $date->between($today->copy()->addYear()->startOfYear(), $today->copy()->addYear()->endOfYear()),
            'date_last_n_years' => $date->between($today->copy()->subYears($n)->startOfYear(), $today->copy()->endOfDay()),
            'date_next_n_years' => $date->between($today->copy()->startOfDay(), $today->copy()->addYears($n)->endOfYear()),
            'date_n_years_ago' => $date->between($today->copy()->subYears($n)->startOfYear(), $today->copy()->subYears($n)->endOfYear()),

            'date_this_financial_year' => self::dateWithinFinancialYear($date, 'this_financial_year'),
            'date_last_financial_year' => self::dateWithinFinancialYear($date, 'last_financial_year'),
            'date_next_financial_year' => self::dateWithinFinancialYear($date, 'next_financial_year'),

            default => false,
        };
    }

    private static function dateWithinFinancialYear(Carbon $date, string $shortcutKey): bool
    {
        // `to` is exclusive; mirror SQL scope semantics (>= from, < to) instead of
        // Carbon::between() so the first instant of the next FY is not matched.
        ['from' => $from, 'to' => $to] = DateShortcutResolver::resolve($shortcutKey);

        return $date->greaterThanOrEqualTo($from) && $date->lessThan($to);
    }
}
