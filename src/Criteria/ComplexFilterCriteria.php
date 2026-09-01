<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use CaueSantos\LaravelRequestFilters\Support\Values;
use Illuminate\Database\Eloquent\Builder;

/**
 * Complex (nested AND/OR) filters:
 *
 *   {"logic": "and", "filters": [
 *       {"column": "status", "operator": "eq", "value": "open"},
 *       {"logic": "or", "filters": [...]},
 *   ]}
 *
 * Groups are applied through nested `where(Closure)`/`orWhere(Closure)`
 * calls - Eloquent's own parenthesised-grouping mechanism - instead of the
 * `toRawSql()`/string-splicing subquery the previous implementation relied
 * on to filter across relations.
 */
class ComplexFilterCriteria extends BaseFilterCriteria
{
    public function apply(): Builder
    {
        $filters = (array) $this->request->get('complexFilters', $this->request->get('filters', []));

        $this->applyGroup($this->builder, $filters);

        return $this->builder;
    }

    private function applyGroup(Builder $query, array $node, string $boolean = 'and'): void
    {
        $children = $node['filters'] ?? null;

        if (empty($children) || !is_array($children)) {
            return;
        }

        $logic = strtolower((string) ($node['logic'] ?? 'and')) === 'or' ? 'or' : 'and';

        $query->where(function (Builder $q) use ($children, $logic) {
            foreach ($children as $child) {
                if (!is_array($child)) {
                    continue;
                }

                if (!empty($child['filters'])) {
                    $this->applyGroup($q, $child, $logic);
                } elseif (!empty($child['column'])) {
                    $this->applyLeaf($q, $child, $logic);
                }
            }
        }, boolean: $boolean);
    }

    private function applyLeaf(Builder $query, array $leaf, string $boolean): void
    {
        $rawField = (string) ($leaf['column'] ?? '');

        if ($rawField === '') {
            return;
        }

        $fields = array_map(fn ($f) => $this->resolveAlias($f), explode(',', $rawField));
        $operatorKey = (string) ($leaf['operator'] ?? 'eq');
        $modifier = $leaf['modifier'] ?? null;
        $rawValue = $leaf['value'] ?? null;

        $value = Values::convertValue(
            is_array($rawValue) ? $rawValue : explode(',', (string) $rawValue)
        );

        foreach ($fields as $field) {
            if (!self::isFieldAllowed($field, $this->criteriaConfig->filterable())) {
                return;
            }
        }

        if (count($fields) === 1) {
            $this->resolveAndApplyField($query, $fields[0], $operatorKey, $modifier, $value, $boolean);

            return;
        }

        $this->applyMultiColumnLeaf($query, $fields, $operatorKey, $modifier, $value, $boolean);
    }

    /**
     * A leaf naming more than one column (`"company.name,company.city"`,
     * typically paired with the `concat` modifier). Supported when every
     * column is local, or every column belongs to the same relation path;
     * columns spanning different relations are not resolvable to a single
     * condition and are silently dropped.
     */
    private function applyMultiColumnLeaf(Builder $query, array $fields, string $operatorKey, ?string $modifier, array $value, string $boolean): void
    {
        $operator = $this->changeOperator($operatorKey);

        $relations = collect($fields)
            ->map(fn ($f) => str_contains($f, '.') ? ColumnResolver::dotRelations($f, true) : null)
            ->filter()
            ->unique();

        if ($relations->count() > 1) {
            return;
        }

        if ($relations->count() === 1) {
            $relationPath = $relations->first();

            if (!$this->checkRelationAllowed($relationPath.'.x') || !$this->relationPathExists($query->getModel(), $relationPath)) {
                return;
            }

            $columns = array_map(fn ($f) => ColumnResolver::getColumnFromDottedRelation($f), $fields);

            $query->has($relationPath, '>=', 1, $boolean, function (Builder $relatedQuery) use ($columns, $operator, $modifier, $value) {
                $resolved = array_map(
                    fn ($c) => ColumnResolver::resolveJsonPath($relatedQuery->qualifyColumn(ColumnResolver::columnNamePolicy($c))),
                    $columns
                );
                $this->applyCondition($relatedQuery, $resolved, $operator, $modifier, $value);
            });

            return;
        }

        $columns = array_map(
            fn ($f) => ColumnResolver::resolveJsonPath($query->qualifyColumn(ColumnResolver::columnNamePolicy($f))),
            $fields
        );

        $this->applyCondition($query, $columns, $operator, $modifier, $value, $boolean);
    }
}
