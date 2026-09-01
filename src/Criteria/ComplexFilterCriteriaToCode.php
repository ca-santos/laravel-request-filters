<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Support\DateShortcuts;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Evaluates the same `{logic, filters: [...]}` filter-tree structure used by
 * {@see ComplexFilterCriteria} against an already-loaded collection of items
 * (models or arrays) in memory, instead of generating SQL. Useful whenever a
 * filter needs to run after the data is already resolved (e.g. evaluating a
 * business rule condition against one record).
 *
 * Attribute values are read with `data_get()`, so it works against Eloquent
 * models (attributes, accessors, loaded relations) and plain arrays alike -
 * no resource/field metadata layer is required.
 *
 * Date-shortcut operators (`date_today`, `date_this_financial_year`, ...) are
 * resolved through {@see DateShortcuts}, the same source of truth the SQL
 * engine uses, so a shortcut means the same range in both places.
 */
final class ComplexFilterCriteriaToCode
{
    /**
     * @param  array<string, Closure(mixed $item, mixed $itemValue, string $operator, mixed $filterValue): mixed>  $valueTransformers
     *                                                                                                                                keyed by attribute - lets a caller post-process a value before comparison (e.g. normalising a status enum)
     */
    public function __construct(private readonly array $valueTransformers = [])
    {
    }

    public function apply(Collection $items, array $filterStructure): Collection
    {
        return $items->filter(fn ($item) => $this->evaluateGroup($item, $filterStructure))->values();
    }

    private function evaluateGroup(mixed $item, array $group): bool
    {
        $logic = strtolower((string) ($group['logic'] ?? 'and'));
        $filters = $group['filters'] ?? [];

        if (empty($filters)) {
            return true;
        }

        $results = array_map(
            fn ($filter) => isset($filter['filters']) ? $this->evaluateGroup($item, $filter) : $this->evaluateFilter($item, $filter),
            $filters
        );

        return $logic === 'or' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    private function evaluateFilter(mixed $item, array $filter): bool
    {
        $attribute = $filter['attribute'] ?? $filter['column'] ?? $filter['field'] ?? null;
        $operator = (string) ($filter['operator'] ?? 'eq');
        $filterValue = $filter['value'] ?? null;

        if ($attribute === null) {
            return false;
        }

        $itemValue = $this->getAttributeValue($item, $attribute);

        if ($transform = $this->valueTransformers[$attribute] ?? null) {
            $itemValue = $transform($item, $itemValue, $operator, $filterValue);
        }

        return self::applyOperator($itemValue, $operator, $filterValue);
    }

    private function getAttributeValue(mixed $item, string $attribute): mixed
    {
        $value = data_get($item, $attribute);

        // A loaded relation collection (BelongsToMany/HasMany): compare by
        // primary keys so `in`/`!in` behave meaningfully against it.
        if ($value instanceof Collection && ($first = $value->first()) instanceof Model) {
            return $value->map(fn (Model $m) => $m->getKey())->all();
        }

        return $value;
    }

    private static function tryParseDate(mixed $value): Carbon|CarbonImmutable|null
    {
        if ($value instanceof Carbon || $value instanceof CarbonImmutable) {
            return $value;
        }

        if (!is_string($value) && !$value instanceof \DateTimeInterface) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
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
        if (str_starts_with($operator, 'date_')) {
            return self::applyDateOperator($itemValue, substr($operator, 5), $filterValue);
        }

        $negated = str_starts_with($operator, '!');
        $base = $negated ? substr($operator, 1) : $operator;

        switch ($base) {
            case 'eq':
                [$a, $b] = self::normalizeForComparison($itemValue, $filterValue);

                return $negated ? $a != $b : $a == $b;

            case 'contains':
                $result = stripos((string) $itemValue, (string) $filterValue) !== false;

                return $negated ? !$result : $result;

            case 'starts':
                $result = stripos((string) $itemValue, (string) $filterValue) === 0;

                return $negated ? !$result : $result;

            case 'ends':
                $result = str_ends_with(strtolower((string) $itemValue), strtolower((string) $filterValue));

                return $negated ? !$result : $result;

            case 'empty':
                return $negated ? !empty($itemValue) : empty($itemValue);

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
                [$min, $max] = array_pad((array) $filterValue, 2, null);
                [$a, $min] = self::normalizeForComparison($itemValue, $min);
                [, $max] = self::normalizeForComparison($itemValue, $max);
                $result = $a >= $min && $a <= $max;

                return $negated ? !$result : $result;

            case 'in':
                $result = is_array($itemValue)
                    ? !empty(array_intersect($itemValue, (array) $filterValue))
                    : in_array($itemValue, (array) $filterValue);

                return $negated ? !$result : $result;

            default:
                return false;
        }
    }

    private static function applyDateOperator(mixed $itemValue, string $key, mixed $n): bool
    {
        if (empty($itemValue)) {
            return false;
        }

        try {
            $date = Carbon::parse($itemValue)->startOfDay();
        } catch (Throwable) {
            return false;
        }

        if (!in_array($key, DateShortcuts::keys(), true)) {
            return false;
        }

        return DateShortcuts::contains($key, $date, (int) $n);
    }
}
