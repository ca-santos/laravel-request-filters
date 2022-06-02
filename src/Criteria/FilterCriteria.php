<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FilterCriteria extends BaseCriteria implements CriteriaContract
{

    /**
     * @return Builder
     */
    function apply(): Builder
    {

        $filters = $this->clearFields($this->request->get('filter', []), 'filterable');

        foreach ($filters as $name => $value) {

            $operator = '=';
            if (is_array($value)) {
                $keyed = array_keys($value);
                $operator = $this->changeOperator($keyed[0]);
                $value = array_values($value);
            } else {
                $value = [$value];
            }

            $multiColumns = $this->getColumns($name);
            $isMultiColumns = count($multiColumns) > 1;

            $relations = explode('.', $multiColumns[0]);

            if (count($relations) > 1) {

                $relation = implode('.', array_slice($relations, 0, -1));
                $column = !$multiColumns ?
                    end($relations) :
                    $this->removeRelationFromColumns($multiColumns);

                /** @var array $definedRelation */
                if ($definedRelation = $this->builder->getModel()->hasDefinedRelation($relation)) {

                    $this->builder = $this->builder->whereHas($relation, function ($query) use ($relation, $name, $column, $operator, $value, $definedRelation) {

                        $name = (explode(':', $name)[0] ?? 'default') . ':' . implode(',', $this->replaceRalationForTable($relation, $definedRelation, $name));

                        return $this->transform(
                            $query,
                            $name,
                            $operator,
                            $value[0]
                        );
                    });

                }

            } else {

                $this->builder = $this->transform($this->builder, $name, $operator, $value[0]);

            }

        }

        return $this->builder;

    }

    function changeOperator($operatorStr): string
    {
        $ops = [
            'eq' => '=',
            'gt' => '>',
            'lt' => '<',
            'gte' => '>=',
            'lte' => '<=',
            'contains' => 'like',
            'ne' => '!='
        ];
        return $ops[$operatorStr] ?: '=';
    }

    private function removeRelationFromColumns(array $columns): array
    {

        $new = [];
        foreach ($columns as $column) {
            $ex = explode('.', $column);
            $new[] = end($ex);
        }

        return $new;

    }

    private function getColumns(string $columnArg): array
    {
        $ex = explode(':', $columnArg);
        return explode(',', count($ex) > 1 ? $ex[1] : $ex[0]);
    }

    private function replaceRalationForTable(string $relation, array $definedRelation, string $columnArg): array
    {

        $ex = explode('.', $relation);
        $relationToTable = end($ex);

        $new = [];
        foreach ($this->getColumns($columnArg) as $column) {
            $new[] = str_replace($relation, $definedRelation[$relationToTable]['table'], $column);
        }
        return $new;

    }

    private function transform(Builder $builder, string $columnArg, string $operator, mixed $value)
    {

        $value = $operator === 'like' ? "%$value%" : $value;

        $converters = [
            'concat' => function (Builder $query, array $columns) use ($operator, $value) {
                return $query->whereRaw("CONCAT(" . implode('," ",', $columns) . ") $operator '$value' ");
            },
            'datetime' => function (Builder $query, array $columns) use ($operator, $value) {
                return $query->whereDate($columns[0], $operator, $value);
            },
            'is_null' => function (Builder $query, array $columns) use ($operator, $value) {
                if ($value == 'true') {
                    return $query->whereNull($columns[0]);
                } else {
                    return $query->whereNotNull($columns[0]);
                }
            },
            'default' => function (Builder $query, array $columns) use ($operator, $value) {
                return $query->where($columns[0], $operator, $value);
            },
        ];

        $ex = explode(':', $columnArg);
        $converter = count($ex) > 1 && array_key_exists($ex[0], $converters) ? $ex[0] : 'default';
        $columns = explode(',', count($ex) > 1 ? $ex[1] : $ex[0]);

        return $converters[$converter]($builder, $columns);

    }

}
