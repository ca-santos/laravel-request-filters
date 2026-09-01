<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use CaueSantos\Brik\Fields\BaseCounterConcern;
use CaueSantos\Brik\Fields\ConcatenationField;
use CaueSantos\Brik\Fields\DynamicSelect;
use CaueSantos\Brik\Fields\FormulaField;
use CaueSantos\Brik\Fields\RelationField;
use CaueSantos\Brik\Filters\Filter;
use CaueSantos\Brik\Filters\RelationFilter;
use CaueSantos\Brik\Http\Requests\BrikFormRequest;
use CaueSantos\LaravelRequestFilters\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** @deprecated Legacy Brik-coupled filter engine, kept only for backward compatibility. See {@see ResourceFilters}. */
class ComplexFilterCriteriaB extends BaseFilterCriteria
{
    public function apply(): Builder
    {
        $this->model = $this->builder->getModel();
        $filters = $this->request->get('filters', $this->request->all()['filters'] ?? []);

        $preWheres = $this->builder->getQuery()->wheres;
        $this->builder->getQuery()->wheres = [];
        $this->builder->getQuery()->havings = null;

        $this->loopFilters($filters, $this->builder);

        /* TODO: Check if everything is working after removing this line */
        // $this->builder->select($this->builder->qualifyColumn('*'));

        $this->builder->getQuery()->wheres = [
            ...$preWheres,
            ...$this->builder->getQuery()->wheres,
        ];

        return $this->builder;
    }

    private function loopFilters($filter, Builder|QueryBuilder $builder, $debug = false): void
    {
        if (!empty($logic = $filter['logic'] ?? 'and') && is_array($filter['filters'] ?? [])) {

            $fn = $logic === 'or' ? 'orWhere' : 'where';

            $builder->{$fn}(function (QueryBuilder|Builder $b) use ($filter, $logic) {

                foreach ($filter['filters'] ?? $filter ?? [] as $key => $f) {

                    /** @var Filter $resourceFilter */
                    $resourceFilter = $this->resourceFilters
                        ->first(function (Filter $filter) use ($f) {
                            return $filter->filterAttribute() === (is_array($f) ? ($f['attribute'] ?? null) : $f);
                        });

                    if ($resourceFilter && property_exists($resourceFilter->field, 'filterUsingCallback') && is_callable($resourceFilter->field?->filterUsingCallback)) {
                        $filterValue = Helpers::convertValue(is_array($f) ? ($f['value'] ?? null) : $f);
                        call_user_func($resourceFilter->field->filterUsingCallback, $b, $resourceFilter, $filterValue, $f['operator'] ?? 'eq');
                    } elseif (!empty($f['filters']) || !empty($f['attribute']) || ($key === 'filters' && is_array($f))) {
                        if ($logic === 'or') {
                            $b->orWhere(fn ($bb) => $this->loopFilters($f, $bb, 3));
                        } else {
                            $b->where(fn ($bb) => $this->loopFilters($f, $bb, 3));
                        }
                    }

                }

            });

        }

        if (!empty($filter['attribute'])) {
            $this->applyOne($filter, $builder, $debug);
        }
    }

    private function applyOne($filter, Builder|QueryBuilder $builder, $debug = false): void
    {
        $requestColumns = $filter['attribute'] ?? null;

        /** @var Filter $schema */
        $schema = $this->resourceFilters->first(function (Filter $f) use ($requestColumns) {
            return implode(',', $f->relationAttributes()) === $requestColumns ||
              $requestColumns === $f->filterAttribute() ||
              $requestColumns === $f->field->attribute ||
              $requestColumns === ($f->field?->relationName ?? null) ||
              $requestColumns === $f->field->uniqueKey();
        });

        $columns = implode(',', $schema?->relationAttributes() ?? explode(',', $requestColumns));

        if (!empty($schema) && $schema->field instanceof DynamicSelect) {
            $columns = $schema->field->attribute;
        }

        $operator = $this->changeOperator($requestOperator = $filter['operator'] ?? 'eq');
        $converter = $filter['modifier'] ?? null;

        $filter['value'] = $filter['value'] ?? null;
        $value = !is_array($filter['value']) ? explode(',', $filter['value']) : $filter['value'];
        $value = Helpers::sanitizeValue($value);

        // Counter fields (HasManyCounter / *WithFilters / BelongsToManyCounter …) are
        // SELECT-alias correlated subqueries. SQL cannot reference a SELECT alias in
        // WHERE, so comparing the alias there throws "Unknown column … in WHERE"
        // (issue #903 — and it fails on the index path too). Translate the comparison
        // to a correlated has() subquery, which is valid in WHERE and needs no GROUP BY.
        $counterField = $this->resolvedCounterField($schema, $requestColumns);
        if ($counterField) {
            $this->applyCounterFilter($builder, $counterField, $requestOperator, $value);

            return;
        }

        $columns = implode(',', $this->uniqueKeyToAttributes($columns));

        $multiColumns = $this->getFilterColumns($columns);

        $relations = collect($multiColumns)->map(function ($col) {
            $ex = explode('.', $col);
            unset($ex[count($ex) - 1]);

            return !empty($ex) ? implode('.', $ex) : null;
        })->filter()->unique()->toArray();

        $explodedRelation = array_values(array_filter(explode('.', $relations[0] ?? '')));

        if (count($explodedRelation) > 0) {

            $relation = $relations[0] ?? null;

            if (!empty($schema)) {

                if ($schema instanceof RelationFilter) {
                    $columns = is_string($columns) ? explode(',', $columns) : (!is_array($columns) ? [$columns] : $columns);
                    $columns = implode(',', !empty($c = $schema->modifyColumns($requestOperator)) ? $c : $columns);
                    $schema->field->lazyResolve($builder, $schema->field->relatedResourceInstance);
                    $schema->field->resolveTitleValue($builder);
                }

            }

            $fn = 'whereHas';
            if ($operator === 'empty') {
                $fn = 'whereDoesntHave';
            }

            // Normalise the relation path to the names Eloquent actually registered.
            // Cross-resource filters (NestedRelation page/counter filters) can carry a
            // leaf segment expressed as a field uniqueKey (e.g. `yll_..._belongs_to`)
            // rather than its registered relation (`yll_..._relation`). Passing the
            // uniqueKey to whereHas() throws "undefined method". Resolve the path to
            // its RelationField chain and rebuild it from each field's baseRelationName.
            $relation = $this->normaliseRelationPath($relation) ?? $relation;

            $builder->{$fn}($relation, function ($q) use ($columns, $operator, $converter, $value) {
                $this->transform(
                    $q->newQuery(),
                    collect(explode(',', trim($columns)))->map(function ($col) use ($q) {
                        $ex = explode('.', $col);

                        return $q->qualifyColumn(end($ex));
                    })->toArray(),
                    $operator,
                    $converter,
                    $value,
                    true
                );
            });

        } else {

            $isComputedField = !empty($schema) &&
              in_array(get_class($schema->field), [FormulaField::class, ConcatenationField::class]);

            // For computed fields (FormulaField / ConcatenationField), use the
            // resolved SQL expression instead of the alias.  SQL does not allow
            // referencing SELECT aliases in WHERE; the full expression works.
            if ($isComputedField && filled($schema->field->resolvedAttribute)) {
                $columns = ['('.$schema->field->resolvedAttribute.')'];
            } else {
                $columns = collect(explode(',', $columns))
                    ->map(fn ($c) => $this::columnNamePolicy(!$isComputedField ? $builder->qualifyColumn($c) : $c))
                    ->toArray();
            }

            $this->transform(
                $builder,
                $columns,
                $operator,
                $converter,
                $value,
                false
            );

        }
    }

    /**
     * Resolve a dotted relation path to its RelationField chain and rebuild it
     * using each field's baseRelationName — the name Eloquent registered the
     * relation under. Returns null when the path can't be resolved to an
     * all-relation chain, so the caller falls back to the original string.
     */
    private function normaliseRelationPath(?string $relation): ?string
    {
        if (empty($relation)) {
            return null;
        }

        $fields = $this->resource->findFieldPathFromDotNotation(
            $relation,
            ['uniqueKey', 'baseRelationName', 'relationName']
        );

        if (empty($fields)) {
            return null;
        }

        $segments = [];
        foreach ($fields as $field) {
            if (!$field instanceof RelationField || empty($field->baseRelationName)) {
                return null;
            }
            $segments[] = $field->baseRelationName;
        }

        return implode('.', $segments);
    }

    /**
     * Compare a relation-counter field by its count via a correlated has() subquery
     * (valid in WHERE, no GROUP BY / SELECT-alias needed). The counter's own
     * configured filters (the *WithFilters variants) define what is counted, so they
     * are re-applied inside the existence check to match the displayed count.
     */
    /**
     * Resolve the relation-counter field for this filter, if any. Prefers the
     * already-matched filter schema (the common path); only when no schema matched —
     * e.g. queue contexts like a Process Builder rule run, where the auth-masked index
     * fields backing $this->resourceFilters are empty — does it fall back to scanning
     * the resource's availableFields().
     */
    private function resolvedCounterField(?Filter $schema, ?string $requestColumns): ?RelationField
    {
        if ($schema && $this->isCounterField($schema->field)) {
            return $schema->field;
        }

        if (!empty($schema) || blank($requestColumns) || !$this->resource) {
            return null;
        }

        return collect($this->resource->availableFields(new BrikFormRequest))
            ->first(fn ($f) => $this->isCounterField($f)
                && in_array($requestColumns, array_filter([
                    $f->relationName ?? null,
                    $f->attribute ?? null,
                    method_exists($f, 'uniqueKey') ? $f->uniqueKey() : null,
                ]), true));
    }

    /**
     * A relation-backed counter (HasManyCounter / BelongsToManyCounter and their
     * *WithFilters subclasses). Progress also implements BaseCounterConcern but is a
     * JSON progress aggregate, not a count comparison — exclude it.
     */
    private function isCounterField($field): bool
    {
        return $field instanceof BaseCounterConcern && $field instanceof RelationField;
    }

    private function applyCounterFilter(Builder|QueryBuilder $builder, RelationField $field, string $requestOperator, array $value): void
    {
        $relation = $field->baseRelationName;

        $constraint = function ($q) use ($field) {
            if (!empty($field->filters)) {
                $request = new BrikFormRequest;
                $request->merge(['filters' => ['logic' => 'and', 'filters' => [$field->filters]]]);

                ResourceFilters::make($q, $field->relatedResourceInstance, $request)->apply();
            }
        };

        $negated = str_starts_with($requestOperator, '!');
        $base = ltrim($requestOperator, '!');
        $nums = array_map('intval', array_values($value));

        if ($base === 'between') {
            $min = $nums[0] ?? 0;
            $max = $nums[1] ?? 0;

            if (!$negated) {
                $builder->has($relation, '>=', $min, 'and', $constraint)
                    ->has($relation, '<=', $max, 'and', $constraint);
            } else {
                $builder->where(function ($w) use ($relation, $constraint, $min, $max) {
                    $w->has($relation, '<', $min, 'and', $constraint)
                        ->has($relation, '>', $max, 'or', $constraint);
                });
            }

            return;
        }

        $map = ['eq' => '=', 'lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>='];
        $operator = $negated && $base === 'eq' ? '!=' : ($map[$base] ?? '=');

        $builder->has($relation, $operator, $nums[0] ?? 0, 'and', $constraint);
    }

    public function changeOperator($operatorStr): string
    {
        return $this->filterOperators[$operatorStr] ?? '=';
    }

    private function getFilterColumns(string $columnArg): array
    {
        return explode(',', $columnArg);
    }

    private function uniqueKeyToAttributes(string $columnArg): array
    {
        $cols = [];
        foreach (explode(',', $columnArg) as $col) {
            // Match each path segment by uniqueKey OR relation name. Cross-resource
            // filters (page filters / counter sub-filters projected onto a related
            // resource) arrive as a MIXED path: the prepended relation prefix is a
            // baseRelationName (e.g. `qba_..._relation`) while the stored leaf is a
            // uniqueKey (e.g. `yll_..._belongs_to`). Matching on uniqueKey alone
            // can't resolve such a path, so the raw uniqueKey leaked through as a
            // relation segment in whereHas() and threw "undefined method". Allowing
            // baseRelationName/relationName lets the whole chain resolve to real
            // relations and rebuild as a valid baseRelationName path.
            if (!empty($fields = $this->resource->findFieldPathFromDotNotation($col, ['uniqueKey', 'baseRelationName', 'relationName']))) {

                $fieldPath = [];

                if (count($fields) === 1 && $fields[0] instanceof RelationField) {
                    $fieldPath = [$fields[0]->getQualifiedRelationAttribute()];
                } else {
                    $lastIdx = count($fields) - 1;
                    foreach ($fields as $idx => $field) {
                        if ($field instanceof DynamicSelect) {
                            $fieldPath[] = $field->attribute;
                        } elseif ($idx === $lastIdx) {
                            // Last field: use attribute (DB column), not relationName (display name)
                            $fieldPath[] = filled($field->attribute) ? $field->attribute : ($field?->as ?? $field?->relationName);
                        } else {
                            $fieldPath[] = $field?->as ?? $field?->relationName ?? $field->attribute;
                        }
                    }
                }

            }
            $cols[] = !empty($fieldPath) ? implode('.', $fieldPath) : $col;
        }

        return $cols;
    }

    private function transform(Builder|QueryBuilder $builder, array $columns, string $operator, mixed $converter, array $value, bool $intoSubQuery = false): void
    {
        $columns = $this->resolveFields($columns);

        $value = Helpers::convertValue($value);
        $this->operatorQuery($builder, $columns, $operator, $converter, $value, $intoSubQuery, false);
    }
}
