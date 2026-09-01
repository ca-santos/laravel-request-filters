<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\CriteriaContract;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use CaueSantos\LaravelRequestFilters\Support\RelationInfo;
use CaueSantos\LaravelRequestFilters\Support\RelationIntrospector;
use CaueSantos\LaravelRequestFilters\Support\SchemaIntrospector;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ordering, including "smart sort" across relations. Every relation hop is
 * resolved through {@see RelationIntrospector} (real Eloquent relation
 * objects), never a consumer-provided metadata registry, so it works with any
 * Eloquent model.
 *
 * An order column/relation path that can't be resolved is dropped silently
 * (the query still runs, just without that particular ordering) rather than
 * failing the request - the same fallback behaviour the engine has always had.
 */
class OrderByCriteria extends BaseCriteria implements CriteriaContract
{
    /** Simple ordering: `order[asc]=col1,col2&order[desc]=col3`. Relation paths are supported (see {@see self::sortColumn()}). */
    public function apply(): Builder
    {
        $orders = (array) $this->request->get('order', []);
        $asc = array_filter(explode(',', (string) ($orders['asc'] ?? '')));
        $desc = array_filter(explode(',', (string) ($orders['desc'] ?? '')));

        $this->checkFields($asc, 'orderable');
        $this->checkFields($desc, 'orderable');

        foreach ($asc as $column) {
            $this->builder = $this->sortColumn($this->builder, $column, 'ASC');
        }

        foreach ($desc as $column) {
            $this->builder = $this->sortColumn($this->builder, $column, 'DESC');
        }

        return $this->builder;
    }

    /**
     * Same as {@see self::apply()} but never throws on a disallowed/unresolvable
     * column (it's dropped instead), and falls back to a sensible default order
     * (the primary key, or `created_at` for a non-integer key) when nothing was
     * requested.
     */
    public function smartSort(array $options = []): Builder
    {
        $model = $this->builder->getModel();
        $defaultDirection = $options['sort_default_direction'] ?? $options['defaultDirection'] ?? 'asc';
        $defaultSortColumn = $model->getKeyType() !== 'int' && $model->usesTimestamps()
            ? $model::CREATED_AT
            : $model->getKeyName();

        $orders = (array) $this->request->get('order', []);
        $requested = $this->normalizeOrders($orders);

        $whitelist = $this->criteriaConfig->orderable();
        $applied = false;

        foreach ($requested as [$column, $direction]) {
            if (!self::isFieldAllowed($column, $whitelist) || !$this->checkRelationAllowed($column)) {
                continue;
            }

            $before = $this->builder;
            $this->builder = $this->sortColumn($this->builder, $column, $direction);
            $applied = $applied || $this->builder !== $before;
        }

        if (!$applied) {
            $this->builder = $this->builder->orderBy($defaultSortColumn, $defaultDirection);
        }

        return $this->builder;
    }

    /**
     * @return list<array{0: string, 1: string}> list of [column, direction] pairs, from either
     *                                            the legacy `{asc: "a,b", desc: "c"}` shape or the
     *                                            `[{column, dir}, ...]` shape.
     */
    private function normalizeOrders(array $orders): array
    {
        if (array_is_list($orders)) {
            return collect($orders)
                ->filter(fn ($o) => is_array($o) && is_string($o['column'] ?? null) && ColumnResolver::isSafeColumnName($o['column']))
                ->map(fn ($o) => [$o['column'], strtolower($o['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC'])
                ->values()
                ->all();
        }

        $pairs = [];
        foreach (array_filter(explode(',', (string) ($orders['asc'] ?? ''))) as $column) {
            $pairs[] = [$column, 'ASC'];
        }
        foreach (array_filter(explode(',', (string) ($orders['desc'] ?? ''))) as $column) {
            $pairs[] = [$column, 'DESC'];
        }

        return $pairs;
    }

    /**
     * Order `$builder` by `$path` (a plain column, a computed field, a custom
     * sort, or a - possibly nested - relation path), adding whatever joins are
     * necessary. Falls back to returning `$builder` unchanged when `$path`
     * can't be resolved to anything sortable.
     */
    private function sortColumn(Builder $builder, string $path, string $direction): Builder
    {
        $path = $this->resolveAlias(Str::replace(':', '.', $path));
        $model = $builder->getModel();
        $extended = $this->extendedCriteria();

        if ($extended && ($custom = $extended->customSorts()[$path] ?? null)) {
            $custom($builder, $direction);

            return $builder;
        }

        if (!str_contains($path, '.')) {
            if ($extended && ($computed = $extended->computedFields()[$path] ?? null)) {
                $expression = $computed->resolve($builder);

                return $builder->orderByRaw("({$expression} IS NULL) {$direction}, {$expression} {$direction}");
            }

            if ($extended && ($counter = $extended->counters()[$path] ?? null)) {
                // A counter is a `withCount()` subselect, not a real column - SQL
                // does not allow qualifying a SELECT alias with the table name in
                // ORDER BY, so it must stay unqualified (unlike every other case
                // here). `relation as alias` is Eloquent's own aggregate-aliasing
                // syntax, so this reuses whatever aggregate the request already
                // added instead of risking a duplicate subselect under the same
                // alias.
                if (!$this->hasAggregateAlias($builder, $path)) {
                    // withCount()'s `relation as alias => constraint` array form requires
                    // a callable constraint (unlike the plain string-list form) - substitute
                    // a no-op when the counter itself has none.
                    $builder->withCount([$counter->relation.' as '.$path => $counter->constraint ?? static fn ($query) => $query]);
                }

                $expression = ColumnResolver::escapeColumn($path);

                return $builder->orderByRaw("({$expression} IS NULL) {$direction}, {$expression} {$direction}");
            }

            $column = ColumnResolver::columnNamePolicy($path);
            $isJson = str_contains($column, '->');
            $qualified = $isJson ? ColumnResolver::resolveJsonPath($builder->qualifyColumn(explode('->', $column)[0]).'->'.Str::after($column, '->'))
                : ColumnResolver::escapeColumn($builder->qualifyColumn($column));

            return $builder->orderByRaw("({$qualified} IS NULL) {$direction}, {$qualified} {$direction}");
        }

        return $this->sortAcrossRelation($builder, $model, $path, $direction);
    }

    /** Whether the query already selects an aggregate subselect aliased `$alias` (e.g. from `count=` or an earlier sort). */
    private function hasAggregateAlias(Builder $builder, string $alias): bool
    {
        $query = $builder->getQuery();
        $wrapped = $query->getGrammar()->wrap($alias);

        foreach ((array) $query->columns as $column) {
            $value = $column instanceof Expression ? $column->getValue($query->getGrammar()) : (string) $column;

            if (str_ends_with(trim((string) $value), 'as '.$wrapped)) {
                return true;
            }
        }

        return false;
    }

    private function sortAcrossRelation(Builder $builder, Model $model, string $path, string $direction): Builder
    {
        $segments = explode('.', $path);
        $column = ColumnResolver::columnNamePolicy(array_pop($segments));

        $baseTable = $model->getTable();
        $baseKey = $model->getKeyName();

        $tableAlias = $baseTable;
        $keyAlias = $baseKey;
        $current = $model;
        $lastInfo = null;

        try {
            foreach ($segments as $relationName) {
                $info = RelationIntrospector::resolve($current, $relationName);

                if (!$info) {
                    return $builder;
                }

                $nextAlias = Str::random(5).'_'.$info->relatedTable;
                $this->joinRelation($builder, $info, $tableAlias, $keyAlias, $nextAlias);

                $tableAlias = $nextAlias;
                $keyAlias = $info->isPivoted() ? $info->pivotRelatedKey : $info->relatedKey;
                $current = new $info->relatedModel;
                $lastInfo = $info;
            }
        } catch (Throwable) {
            return $builder;
        }

        $relatedColumns = SchemaIntrospector::columnNames($current);
        if ($relatedColumns !== [] && !in_array($column, $relatedColumns, true)) {
            // Not a real column on the related model - nothing generic left to try.
            return $builder;
        }

        $quotedColumn = "`{$tableAlias}`.`{$column}`";
        $orderExpr = $lastInfo && $lastInfo->isPivoted() ? "MIN({$quotedColumn})" : $quotedColumn;

        $builder->orderByRaw("({$orderExpr} IS NULL) {$direction}, {$orderExpr} {$direction}");
        $builder->groupBy("{$baseTable}.{$baseKey}");

        return $builder;
    }

    private function joinRelation(Builder $builder, RelationInfo $info, string $fromAlias, string $fromKey, string $toAlias): void
    {
        if ($info->isPivoted()) {
            $pivotAlias = Str::random(5).'_'.$info->pivotTable;

            $builder->leftJoin(
                "{$info->pivotTable} as {$pivotAlias}",
                "{$fromAlias}.{$fromKey}",
                '=',
                "{$pivotAlias}.{$info->pivotForeignKey}"
            )->leftJoin(
                "{$info->relatedTable} as {$toAlias}",
                "{$toAlias}.{$info->relatedKey}",
                '=',
                "{$pivotAlias}.{$info->pivotRelatedKey}"
            );

            return;
        }

        if ($info->isThrough()) {
            $throughAlias = Str::random(5).'_'.$info->throughTable;

            $builder->leftJoin(
                "{$info->throughTable} as {$throughAlias}",
                "{$fromAlias}.{$fromKey}",
                '=',
                "{$throughAlias}.{$info->throughFirstKey}"
            )->leftJoin(
                "{$info->relatedTable} as {$toAlias}",
                "{$throughAlias}.{$info->throughSecondLocalKey}",
                '=',
                "{$toAlias}.{$info->relatedKey}"
            );

            return;
        }

        [$first, $second] = $info->isReverseForeignKey
            ? ["{$toAlias}.{$info->relatedKey}", "{$fromAlias}.{$info->foreignKey}"]
            : ["{$fromAlias}.{$info->foreignKey}", "{$toAlias}.{$info->relatedKey}"];

        $builder->leftJoin("{$info->relatedTable} as {$toAlias}", $first, '=', $second);
    }
}
