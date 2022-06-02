<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use App\Core\Helpers;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;

class OrderByCriteria extends BaseCriteria implements CriteriaContract
{

    function apply(): Builder
    {

        $orders = $this->request->get('order', []);

        if (!isset($orders['asc'])) $orders['asc'] = null;
        if (!isset($orders['desc'])) $orders['desc'] = null;

        $asc = array_filter(explode(',', $orders['asc']));
        $desc = array_filter(explode(',', $orders['desc']));

        $this->checkFields($asc, 'orderable');
        $this->checkFields($desc, 'orderable');

        if (isset($orders['asc'])) {
            foreach ($asc as $column) {
                $this->builder = $this->builder->orderBy($column, 'ASC');
            }
        }

        if (isset($orders['desc'])) {
            foreach ($desc as $column) {
                $this->builder = $this->builder->orderBy($column, 'DESC');
            }
        }

        return $this->builder;

    }

    /**
     * @param string $orderBy
     * @param string $direction
     * @return array
     */
    private function sort(string $orderBy, string $direction = 'ASC'): array
    {
        $builder = $this->builder;

        $model = $builder->getModel();

        $query = $model->newQuery();

        $ex = explode('.', $orderBy);
        $hasRelation = count($ex) > 1;

        try {

            if ($hasRelation) {

                $relation = array_slice($ex, 0, -1);
                $relationDotted = implode('.', $relation);
                $column = end($ex);

                if ($definedRelations = $model->hasDefinedRelation($relationDotted)) {

                    $definedRelations = collect($definedRelations);
                    $columToSelect = $column;

                    $baseTable = $model->getTable();
                    $baseTableAlias = $baseTable;
                    $basePrimaryKey = $model->getKeyName();
                    $basePrimaryKeyAlias = "{$baseTableAlias}_{$basePrimaryKey}";

                    $tableNow = $baseTable;
                    $keyNow = $basePrimaryKey;
                    $key = 0;
                    foreach ($definedRelations as $r) {

                        $table = $r['table'];
                        $tableAlias = Str::random(5) . "_{$table}";
                        $primary = $r['primary'];
                        $foreign = $r['foreign_key'];

                        if (!empty($r['pivot'])) {

                            $pivotTable = $r['pivot']['table'];
                            $pivotTableAlias = Str::random(5) . "_{$pivotTable}";
                            $pivotForeign = $r['pivot']['foreign_key'];
                            $pivotRelated = $r['pivot']['related_key'];

                            $query = $query
                                ->leftJoin(
                                    $pivotTable . " as {$pivotTableAlias}",
                                    "{$tableNow}.{$keyNow}",
                                    '=',
                                    "{$pivotTableAlias}.{$pivotForeign}"
                                )
                                ->leftJoin(
                                    $table . " as {$tableAlias}",
                                    "{$tableAlias}.{$primary}",
                                    '=',
                                    "{$pivotTableAlias}.{$pivotRelated}"
                                );

                        } else {
                            $query = $query
                                ->leftJoin(
                                    $table . " as {$tableAlias}",
                                    "{$tableNow}.{$foreign}",
                                    '=',
                                    "{$tableAlias}.{$primary}"
                                );
                        }

                        $tableNow = $tableAlias;
                        $keyNow = $primary;

                        $key++;

                        if ($key === count($definedRelations)) {
                            $columToSelect = $tableAlias . '.' . $column;
                            $orderBy = str_replace($relationDotted, $tableAlias, $orderBy);
                        }

                    }

                    $select = [
                        "{$baseTable}.{$basePrimaryKey}" . " as {$basePrimaryKeyAlias}",
                        "{$columToSelect}"
                    ];

                    $query = $query->select($select);

                    $query = $query->orderByRaw("ISNULL($orderBy), $orderBy $direction");

                    try {
                        $orderedIds = $query->get()->pluck($basePrimaryKeyAlias)->toArray();
                    } catch (Exception $ex) {
                        $orderedIds = null;
                    }

                    $query = $builder->with($relationDotted);
                    $query = $orderedIds ? $query->whereIn($basePrimaryKey, $orderedIds) : $query;

                    return [
                        'query' => $query,
                        'ordered_ids' => $orderedIds
                    ];

                }

            }

            return [
                'query' => $query->orderByRaw("ISNULL($orderBy), $orderBy $direction"),
                'ordered_ids' => null
            ];

        } catch (Exception $e) {

            return [
                'query' => $query,
                'ordered_ids' => null
            ];

        }

    }

    /**
     * @throws Exception
     */
    #[ArrayShape([0 => Builder::class, 1 => 'string[]', 2 => 'string[]'])]
    public function smartSort(array $options = []): array
    {

        $model = $this->builder->getModel();

        $defaultDirection = $options['sort_default_direction'] ?? 'ASC';

        $defaultSortColumn = $model->getKeyName();

        $orders = $this->request->get('order', []);

        if ($model->getKeyType() !== 'int' && $model->timestamps) {
            $defaultSortColumn = 'created_at';
        }

        $orders['asc'] = $orders['asc'] ?? null;
        $orders['desc'] = $orders['desc'] ?? null;

        $asc = array_filter(explode(',', $orders['asc']));
        $desc = array_filter(explode(',', $orders['desc']));

        $this->checkFields($asc, 'orderable');
        $this->checkFields($desc, 'orderable');

        $ascColumns = [];
        $descColumns = [];
        $orderedIds = [];

        foreach ($asc as $column) {
            $sort = $this->sort($column);
            $this->builder = $sort['query'];
            $ascColumns[] = $column;
            $orderedIds = $sort['ordered_ids'] ?? $orderedIds;
        }

        foreach ($desc as $column) {
            $sort = $this->sort($column, 'DESC');
            $this->builder = $sort['query'];
            $descColumns[] = $column;
            $orderedIds = $sort['ordered_ids'] ?? $orderedIds;
        }

        if (!$orders['asc'] && !$orders['desc']) {
            $this->builder = $this->builder->orderBy($defaultSortColumn, $defaultDirection);
        }

        return [
            $this->builder,
            $ascColumns,
            $descColumns,
            $orderedIds
        ];

    }

    public static function collectionSort(Collection|array $results, $columns = [], $direction = 'ASC'): Collection
    {

        $results = is_array($results) ? collect($results) : $results;

        foreach ($columns as $column) {

            $relations = explode('.', $column);

            if (count($relations) > 1) {

                $sort = array_pop($relations);
                $relations = implode('.', $relations);

                $sortFn = function ($item, $key) use ($relations, $sort) {
                    $dot = Arr::dot($item->toArray());
                    if (isset($dot[$relations . '.0.' . $sort])) {
                        return $dot[$relations . '.0.' . $sort];
                    }
                    return isset($dot[$relations . '.' . $sort]) ? strtolower($dot[$relations . '.' . $sort]) : null;
                };

                if ($direction === 'DESC') {

                    $results = $results->sortByDesc($sortFn);

                } else {

                    $results = $results->sortBy($sortFn);

                }

            }

        }

        return $results;

    }

    public static function isCollectionSort(Builder $builder, string $column): bool
    {

        $relations = explode('.', $column);

        if (
            (count($relations) === 1 && $builder->getModel()->hasDefinedRelation(implode('.', $relations)) !== false) ||
            count($relations) > 1
        ) {
            return true;
        }

        return false;

    }

}
