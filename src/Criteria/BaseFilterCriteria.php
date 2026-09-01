<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\CriteriaContract;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use CaueSantos\LaravelRequestFilters\Support\DateShortcuts;
use CaueSantos\LaravelRequestFilters\Support\RelationCounter;
use CaueSantos\LaravelRequestFilters\Support\RelationIntrospector;
use CaueSantos\LaravelRequestFilters\Support\Values;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The operator vocabulary and condition builder shared by every filtering
 * stage ({@see FilterCriteria}, {@see ComplexFilterCriteria}).
 *
 * Every operator here is built with query bindings or structured Query
 * Builder methods (`where`, `whereIn`, `whereBetween`, `whereDate`, ...) -
 * user-supplied values are never interpolated into raw SQL. The only raw SQL
 * fragments this class ever builds are made exclusively from already
 * whitelisted/escaped *column identifiers* (never from request values), for
 * cases structured methods cannot express: multi-column virtual comparisons
 * (`CONCAT(...)`) and the `LIKE ... ESCAPE ?` clause needed for correct
 * wildcard-escaping across database engines (SQLite has no implicit escape
 * character, unlike MySQL/MariaDB).
 */
abstract class BaseFilterCriteria extends BaseCriteria implements CriteriaContract
{
    /** Canonical operator vocabulary. Negated variants are handled generically (a leading `!`). */
    public const OPERATORS = [
        'eq' => '=', '!eq' => '!=',
        'lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>=',
        'contains' => 'contains', '!contains' => 'contains',
        'starts' => 'starts', '!starts' => 'starts',
        'ends' => 'ends', '!ends' => 'ends',
        'empty' => 'empty', '!empty' => 'empty',
        'in' => 'in', '!in' => 'in',
        'between' => 'between', '!between' => 'between',
    ];

    public function changeOperator(string $operator): string
    {
        if (isset(self::OPERATORS[$operator]) || self::isDateShortcutOperator($operator)) {
            return $operator;
        }

        return 'eq';
    }

    /** Date shortcuts are requested as `date_<key>` (e.g. `date_today`, `date_this_financial_year`). */
    public static function isDateShortcutOperator(string $operator): bool
    {
        return str_starts_with($operator, 'date_') && in_array(substr($operator, 5), DateShortcuts::keys(), true);
    }

    private function stripNegation(string $operator): string
    {
        return str_starts_with($operator, '!') ? substr($operator, 1) : $operator;
    }

    /**
     * Apply one condition to `$query`. `$columns` is one or more already
     * whitelisted, real SQL expressions (a plain qualified column name, or a
     * developer-authored computed-field expression) - never raw user input.
     *
     * @param  list<string>  $columns
     * @param  list<mixed>  $value
     */
    protected function applyCondition(
        Builder $query,
        array $columns,
        string $operator,
        ?string $modifier,
        array $value,
        string $boolean = 'and'
    ): void {
        $negated = str_starts_with($operator, '!');
        $base = $this->stripNegation($operator);

        if ($modifier === 'concat') {
            $columns = [$this->concatExpression($columns)];
        }

        if (self::isDateShortcutOperator($operator)) {
            $this->applyDateShortcut($query, $columns, substr($base, 5), $value, $boolean);

            return;
        }

        match ($base) {
            'empty' => $this->applyEmpty($query, $columns, $negated, $boolean),
            'in' => $this->applyIn($query, $columns, $value, $negated, $boolean),
            'between' => $this->applyBetween($query, $columns, $value, $negated, $boolean),
            'contains' => $this->applyLike($query, $columns, '%%value%%', $value, $negated, $boolean),
            'starts' => $this->applyLike($query, $columns, 'value%%', $value, $negated, $boolean),
            'ends' => $this->applyLike($query, $columns, '%%value', $value, $negated, $boolean),
            default => $this->applyComparison($query, $columns, self::OPERATORS[$operator] ?? '=', $value, $boolean),
        };
    }

    private function applyEmpty(Builder $query, array $columns, bool $negated, string $boolean): void
    {
        $query->where(function ($q) use ($columns, $negated) {
            foreach ($columns as $column) {
                if ($negated) {
                    $q->whereNotNull($column)->where($column, '!=', '');
                } else {
                    $q->orWhereNull($column)->orWhere($column, '=', '');
                }
            }
        }, boolean: $boolean);
    }

    private function applyIn(Builder $query, array $columns, array $value, bool $negated, string $boolean): void
    {
        $query->where(function ($q) use ($columns, $value, $negated) {
            foreach ($columns as $column) {
                $negated ? $q->whereNotIn($column, $value) : $q->orWhereIn($column, $value);
            }
        }, boolean: $boolean);
    }

    private function applyBetween(Builder $query, array $columns, array $value, bool $negated, string $boolean): void
    {
        $range = [$value[0] ?? null, $value[1] ?? null];

        $query->where(function ($q) use ($columns, $range, $negated) {
            foreach ($columns as $column) {
                $negated ? $q->whereNotBetween($column, $range) : $q->orWhereBetween($column, $range);
            }
        }, boolean: $boolean);
    }

    /** @param 'contains'|'starts'|'ends'|string $pattern one of `%%value%%`, `value%%`, `%%value` (`%%` marks a wildcard side) */
    private function applyLike(Builder $query, array $columns, string $pattern, array $value, bool $negated, string $boolean): void
    {
        $needle = Values::escapeLikePattern((string) ($value[0] ?? ''));
        $sqlPattern = str_replace(['%%value%%', 'value%%', '%%value'], ["%{$needle}%", "{$needle}%", "%{$needle}"], $pattern);

        $expression = count($columns) === 1 ? ColumnResolver::escapeColumn($columns[0]) : $this->concatExpression($columns);
        $fn = $negated ? 'NOT LIKE' : 'LIKE';

        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $query->{$method}("{$expression} {$fn} ? ESCAPE ?", [$sqlPattern, Values::LIKE_ESCAPE_CHAR]);
    }

    private function applyDateShortcut(Builder $query, array $columns, string $key, array $value, string $boolean): void
    {
        $n = DateShortcuts::requiresN($key) ? (int) ($value[0] ?? 0) : null;
        $range = DateShortcuts::range($key, $n);
        $closed = DateShortcuts::isClosedRange($key);

        $query->where(function ($q) use ($columns, $range, $closed) {
            foreach ($columns as $column) {
                $q->where($column, '>=', $range->from);
                $q->where($column, $closed ? '<=' : '<', $range->to);
            }
        }, boolean: $boolean);
    }

    private function applyComparison(Builder $query, array $columns, string $operator, array $value, string $boolean): void
    {
        $raw = $value[0] ?? null;

        if (count($columns) === 1) {
            $column = $columns[0];

            if (is_string($raw) && Carbon::hasFormat($raw, 'Y-m-d')) {
                $query->whereDate($column, $operator, $raw, $boolean);
            } else {
                $query->where($column, $operator, $raw, $boolean);
            }

            return;
        }

        $expression = $this->concatExpression($columns);
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $query->{$method}("{$expression} {$operator} ?", [$raw]);
    }

    /** Build a `CONCAT(COALESCE(col, ''), ' - ', ...)` expression from real column identifiers. */
    protected function concatExpression(array $columns): string
    {
        if (count($columns) === 1) {
            return ColumnResolver::escapeColumn($columns[0]);
        }

        $parts = collect($columns)
            ->map(fn ($col) => 'COALESCE('.ColumnResolver::escapeColumn($col).", '')")
            ->implode(", ' - ', ");

        return "CONCAT({$parts})";
    }

    /**
     * The single per-field decision point shared by {@see FilterCriteria} and
     * {@see ComplexFilterCriteria}: resolve `$field` against the criteria's
     * declared capabilities (custom filter, counter, computed field, relation
     * path, plain column) and apply the condition through whichever mechanism
     * is correct for it.
     */
    protected function resolveAndApplyField(
        Builder $query,
        string $field,
        string $operatorKey,
        ?string $modifier,
        array $value,
        string $boolean = 'and'
    ): void {
        $field = $this->resolveAlias($field);
        $operator = $this->changeOperator($operatorKey);
        $extended = $this->extendedCriteria();

        if ($extended && ($callback = $extended->customFilters()[$field] ?? null)) {
            $query->where(fn ($q) => $callback($q, $operator, $value[0] ?? $value, $boolean), boolean: $boolean);

            return;
        }

        if ($extended && ($counter = $extended->counters()[$field] ?? null)) {
            $this->applyCounter($query, $counter, $operator, $value, $boolean);

            return;
        }

        if ($extended && ($computed = $extended->computedFields()[$field] ?? null)) {
            $expression = '('.$computed->resolve($query).')';
            $this->applyCondition($query, [$expression], $operator, $modifier, $value, $boolean);

            return;
        }

        if (str_contains($field, '.')) {
            $this->applyRelationField($query, $field, $operator, $modifier, $value, $boolean);

            return;
        }

        if (!$this->checkRelationAllowed($field)) {
            return;
        }

        $column = ColumnResolver::columnNamePolicy($field);
        $column = $query->qualifyColumn($column);
        $column = ColumnResolver::resolveJsonPath($column);

        $this->applyCondition($query, [$column], $operator, $modifier, $value, $boolean);
    }

    /**
     * A dotted `relation.column` (or `relation.nested.column`) field. Every
     * segment is validated against a real Eloquent relation - an unresolvable
     * relation silently drops the condition (consistent with how an unknown
     * plain column is dropped by the whitelist), rather than raising. Eloquent
     * natively resolves an arbitrarily deep dotted relation path passed to
     * `whereHas()`/`whereDoesntHave()`, so no manual join-building is needed
     * for filtering (only sorting needs that - see {@see OrderByCriteria}).
     */
    private function applyRelationField(Builder $query, string $field, string $operator, ?string $modifier, array $value, string $boolean): void
    {
        if (!$this->checkRelationAllowed($field)) {
            return;
        }

        $relationPath = ColumnResolver::dotRelations($field, true);
        $column = ColumnResolver::getColumnFromDottedRelation($field);

        if (!$this->relationPathExists($query->getModel(), $relationPath)) {
            return;
        }

        $negated = str_starts_with($operator, '!');
        $base = str_starts_with($operator, '!') ? substr($operator, 1) : $operator;

        if ($base === 'empty') {
            // "empty" on a relation means "has no related row at all" - the
            // column/value is irrelevant, only existence is checked. Uses
            // has()/doesntHave() directly (not the whereHas()/whereDoesntHave()
            // convenience wrappers) since only those accept a $boolean.
            $negated
                ? $query->has($relationPath, '>=', 1, $boolean)
                : $query->doesntHave($relationPath, $boolean);

            return;
        }

        $query->has($relationPath, '>=', 1, $boolean, function (Builder $relatedQuery) use ($column, $operator, $modifier, $value) {
            $relatedColumn = $relatedQuery->qualifyColumn(ColumnResolver::columnNamePolicy($column));
            $this->applyCondition($relatedQuery, [ColumnResolver::resolveJsonPath($relatedColumn)], $operator, $modifier, $value);
        });
    }

    private function relationPathExists(Model $model, string $path): bool
    {
        return RelationIntrospector::resolveChain($model, $path) !== null;
    }

    /**
     * Rewrite a relation-count comparison (`tasks_count >= 5`) into a
     * correlated existence check via `has()` - SQL cannot compare a SELECT
     * alias in a WHERE clause, so the counter's own value is never compared
     * directly. `$counter->constraint`, if any, is re-applied inside the
     * check so the count being compared matches what is actually displayed.
     */
    protected function applyCounter(Builder $query, RelationCounter $counter, string $operator, array $value, string $boolean = 'and'): void
    {
        $negated = str_starts_with($operator, '!');
        $base = $negated ? substr($operator, 1) : $operator;
        $numbers = array_map('intval', array_values($value));
        $constraint = $counter->constraint;

        if ($base === 'between') {
            $min = $numbers[0] ?? 0;
            $max = $numbers[1] ?? 0;

            $query->where(function ($w) use ($counter, $min, $max, $constraint, $negated) {
                if (!$negated) {
                    $w->has($counter->relation, '>=', $min, 'and', $constraint)
                        ->has($counter->relation, '<=', $max, 'and', $constraint);
                } else {
                    $w->has($counter->relation, '<', $min, 'and', $constraint)
                        ->has($counter->relation, '>', $max, 'or', $constraint);
                }
            }, boolean: $boolean);

            return;
        }

        $map = ['eq' => '=', 'lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>='];
        $sqlOperator = $negated && $base === 'eq' ? '!=' : ($map[$base] ?? '=');

        $query->has($counter->relation, $sqlOperator, $numbers[0] ?? 0, $boolean, $constraint);
    }
}
