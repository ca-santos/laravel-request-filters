<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\AppBuilder;
use App\Core\Support\ClassPropertyHelper;
use CaueSantos\Brik\Fields\RelationField;
use CaueSantos\LaravelModelUtils\Traits\RelationshipsTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/** @deprecated Legacy Brik-coupled sort engine, kept only for backward compatibility. See {@see \CaueSantos\LaravelRequestFilters\Criteria\OrderByCriteria}. */
class OrderByCriteria extends BaseCriteria implements CriteriaContract
{
    public function apply(): Builder
    {
        return $this->smartSort([]);
    }

    private function joinSort(string $orderBy, string $direction = 'ASC'): Builder
    {

        /** @var Builder|Model|RelationshipsTrait $model */
        $model = $this->builder->getModel();
        $modelTableColumn = "{$model->getTable()}.{$model->getKeyName()}";

        /** @var AppBuilder $builder */
        $builder = clone $this->builder;

        $orderBy = Str::replace(':', '.', $orderBy);
        $ex = explode('.', $orderBy);
        $hasRelation = count($ex) > 1;
        $column = $this->columnNamePolicy(end($ex));
        $isJsonColumn = Str::contains($column, '->');
        array_pop($ex);
        $ex[] = $column;
        $tableColumns = $model->getTableColumns();

        $orderBy = implode('.', $ex);
        if (count($ex) === 1 && in_array($orderBy, $tableColumns)) {
            $orderBy = "{$model->getTable()}.{$orderBy}";
        }

        try {

            if ($hasRelation) {

                $relation = array_slice($ex, 0, -1);
                $relationDotted = implode('.', $relation);
                $column = end($ex);

                if ($definedRelations = $model->hasDefinedRelation($relationDotted)) {

                    $definedRelations = collect($definedRelations);

                    $baseTable = $model->getTable();
                    $basePrimaryKey = $model->getKeyName();

                    $tableNow = $baseTable;
                    $keyNow = $basePrimaryKey;
                    $key = 0;
                    $hasSomeOrder = false;

                    foreach ($relation as $deepRelation) {

                        if ($r = $definedRelations[$deepRelation] ?? null) {

                            $table = $r['table'];
                            $tableAlias = Str::random(5)."_{$table}";
                            $primary = $r['primary'];
                            $foreign = $r['foreign_key'];
                            $isReverseFK = false;

                            if (!empty($r['through'])) {

                                $predecessorAlias = $tableNow;
                                foreach ((array) $r['through'] as $i => $through) {

                                    $tableAlias = Str::random(5)."_{$through['first_table']}";

                                    $builder
                                        ->leftJoin(
                                            "{$through['first_table']} as $tableAlias",
                                            "{$tableAlias}.{$through['first']}",
                                            '=',
                                            "{$predecessorAlias}.{$through['second']}"
                                        );

                                    $predecessorAlias = $tableAlias;

                                }

                            } elseif (!empty($r['pivot'])) {

                                $pivotTable = $r['pivot']['table'];
                                $pivotTableAlias = Str::random(5)."_{$pivotTable}";
                                $pivotForeign = $r['pivot']['foreign_key'];
                                $pivotRelated = $r['pivot']['related_key'];

                                $builder
                                    ->leftJoin(
                                        $pivotTable." as {$pivotTableAlias}",
                                        "{$tableNow}.{$keyNow}",
                                        '=',
                                        "{$pivotTableAlias}.{$pivotForeign}"
                                    )
                                    ->leftJoin(
                                        $table." as {$tableAlias}",
                                        "{$tableAlias}.{$primary}",
                                        '=',
                                        "{$pivotTableAlias}.{$pivotRelated}"
                                    );

                            } else {

                                $isReverseFK = ClassPropertyHelper::objectIsA($r['typeQualified'] ?? '', HasMany::class) ||
                                               ClassPropertyHelper::objectIsA($r['typeQualified'] ?? '', HasOne::class);

                                if ($isReverseFK) {
                                    $localKey = $r['local_key'] ?? $keyNow;
                                    $first = "{$tableAlias}.{$primary}";
                                    $second = "{$tableNow}.{$localKey}";
                                } else {
                                    $first = "{$tableNow}.{$foreign}";
                                    $second = "{$tableAlias}.{$primary}";
                                }

                                $builder
                                    ->leftJoin(
                                        $table." as {$tableAlias}",
                                        $first,
                                        '=',
                                        $second
                                    );

                            }

                            $tableNow = $tableAlias;
                            $keyNow = $isReverseFK ? $foreign : $primary;

                            $key++;
                            $hasSomeOrder = true;

                            if ($key === count($definedRelations)) {

                                // When $column is not a real column (e.g. relation name or ConcatenationField),
                                // resolve it to a real sortable column, potentially adding extra JOINs.
                                $resolved = $this->resolveNonColumnSort($builder, $column, $tableAlias, $r['model']);

                                if ($resolved === false) {
                                    $hasSomeOrder = false;
                                } elseif (is_array($resolved)) {
                                    [$column, $tableAlias] = $resolved;
                                }

                                if ($hasSomeOrder) {
                                    if (!empty($r['pivot'])) {
                                        $orderBy = "MIN(`{$tableAlias}`.`{$column}`)";
                                    } else {
                                        $orderBy = "`{$tableAlias}`.`{$column}`";
                                    }
                                }
                            }

                        }

                    }

                    if (!$hasSomeOrder) {
                        return $this->builder;
                    }

                    return $builder
                        ->orderByRaw("ISNULL($orderBy) $direction, $orderBy $direction")
                        ->groupBy($modelTableColumn);

                }

                return $this->builder;

            }

            $orderBy = $this->resolveFields([$orderBy], $model->getTable())[0];
            $orderBy = $isJsonColumn ? $orderBy : BaseCriteria::escapeColumn($orderBy);

            return $builder
                ->orderByRaw("ISNULL($orderBy) $direction, $orderBy $direction");

        } catch (Exception $e) {
            if (app()->hasDebugModeEnabled()) {
                throw $e;
            }

            return $this->builder;
        }

    }

    /**
     * When the final $column in a relation sort is not a real DB column
     * (e.g. it's a relation name or a ConcatenationField alias),
     * resolve it to a real sortable column, adding extra JOINs as needed.
     *
     * @return null|false|array{0: string, 1: string}
     *                                                null  = column already exists, no changes needed
     *                                                false = unresolvable, skip ordering
     *                                                array = [resolvedColumn, resolvedTableAlias]
     */
    private function resolveNonColumnSort(Builder $builder, string $column, string $tableAlias, string $modelClass): null|false|array
    {
        /** @var Model|RelationshipsTrait $model */
        $model = new $modelClass;
        $columns = $model->getTableColumns();

        if (in_array($column, $columns)) {
            return null;
        }

        $relations = $model->relationships();

        // Case 1: Column is an Eloquent relation name — join through it
        if (isset($relations[$column])) {
            return $this->joinThroughRelation($builder, $relations[$column], $tableAlias);
        }

        // Case 2: Column is a ConcatenationField/FormulaField — resolve first real sortable attribute
        $resource = \CaueSantos\Brik\Brik::resourceForModel($modelClass);

        if (!$resource) {
            return false;
        }

        $fields = collect($resource->fields(app(\CaueSantos\Brik\Http\Requests\BrikFormRequest::class)));

        $concatField = $fields->first(fn ($f) => ($f->attribute === $column || (property_exists($f, 'as') && $f->as === $column))
          && property_exists($f, 'formulaAttributes'));

        if (!$concatField) {
            return false;
        }

        foreach ($concatField->formulaAttributes as $formulaAttr) {
            $formulaAttr = str_replace('.*', '', $formulaAttr);

            if (Str::contains($formulaAttr, '.')) {
                continue;
            }

            $fieldDef = $fields->first(fn ($f) => ($f?->relationName ?? $f->attribute) === $formulaAttr || $f->attribute === $formulaAttr);

            if ($fieldDef instanceof RelationField) {
                $relName = $fieldDef->baseRelationName ?? $fieldDef->relationName;
                if (isset($relations[$relName])) {
                    return $this->joinThroughRelation($builder, $relations[$relName], $tableAlias);
                }
            } elseif ($fieldDef && in_array($fieldDef->attribute, $columns)) {
                return [$fieldDef->attribute, $tableAlias];
            }
        }

        return false;
    }

    /**
     * Add a LEFT JOIN through a relation and return the resolved title column.
     *
     * @return array{0: string, 1: string} [column, tableAlias]
     */
    private function joinThroughRelation(Builder $builder, array $relation, string $fromAlias): array
    {
        $table = $relation['table'];
        $alias = Str::random(5)."_{$table}";
        $primary = $relation['primary'];

        $builder->leftJoin(
            "{$table} as {$alias}",
            "{$fromAlias}.{$relation['foreign_key']}",
            '=',
            "{$alias}.{$primary}"
        );

        $resource = \CaueSantos\Brik\Brik::resourceForModel($relation['model']);

        $column = $resource?->title ?? $primary;

        $model = new $relation['model'];
        if (!in_array($column, $model->getTableColumns())) {
            $column = $primary;
        }

        return [$column, $alias];
    }

    public function smartSort(array $options = []): Builder|Model
    {

        $defaultDirection = $options['defaultDirection'] ?? 'asc';
        $defaultSortColumn = $this->model->getKeyName();

        $requestedOrders = collect($this->request->get('order', []))
            ->filter(function ($o) {
                $column = $o['column'] ?? '';

                return is_string($column)
                  && preg_match('/^[a-zA-Z0-9_.:]+(?:->[a-zA-Z0-9_.]+)*$/', $column);
            });

        if ($this->model->getKeyType() !== 'int' && $this->model->timestamps) {
            $defaultSortColumn = $this->model::CREATED_AT;
        }

        $orders = new Fluent([
            'asc' => $requestedOrders->where('dir', 'asc')->pluck('column'),
            'desc' => $requestedOrders->where('dir', 'desc')->pluck('column'),
        ]);

        foreach ($orders->asc as $column) {
            $this->builder = $this->joinSort($column);
        }

        foreach ($orders->desc as $column) {
            $this->builder = $this->joinSort($column, 'DESC');
        }

        if ($orders->asc?->isEmpty() && $orders->desc?->isEmpty()) {
            $this->builder = $this->builder->orderBy($defaultSortColumn, $defaultDirection);
        }

        return $this->builder;

    }
}
