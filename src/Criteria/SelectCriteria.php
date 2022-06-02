<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Illuminate\Database\Eloquent\Builder;

class SelectCriteria extends BaseCriteria implements CriteriaContract
{

    /**
     * @throws \Exception
     */
    function apply(): Builder
    {
        $selectsFromQuery = $this->request->get('select', []);
        $selectsOriginal = explode(',', $selectsFromQuery);
        $selects = $selectsOriginal;
        $this->checkFields($selects, 'selectable');

        $selectsNoRelation = array_filter($selectsOriginal, function ($item) {
            return !str_contains($item, '.');
        });

        $selectsNoRelation = empty($selectsNoRelation) ? '*' : $selectsNoRelation;

        $this->builder = $this->getRelationFields($selects, $selectsNoRelation);

        return $this->builder;
    }

    function getRelationFields($requestFields, $selectsNoRelation): Builder
    {

        $relations = [];

        foreach ($requestFields as $field) {

            $explodedField = explode('.', $field);

            if (isset($explodedField[1])) {

                $selectField = array_pop($explodedField);
                $relation = implode('.', $explodedField);

                if (!isset($relations[$relation])) {
                    $relations[$relation] = ['id'];
                }

                $relations[$relation][] = $selectField;

            }

        }

        foreach ($relations as $relation => $fields) {

            $relatedModelData = $this->builder->getModel()->hasDefinedRelation($relation);
            $byEager = $this->builder->with($relation)->getEagerLoads();

            if ($relatedModelData !== false) {

                $relatedModel = new $relatedModelData['model']();

                $this->builder = $this->builder->with($relation, function ($query) use ($fields, $relatedModel) {
                    $fields = array_merge($fields, $relatedModel->getForeignKeys());
                    $query->select($fields);
                });

                $selectsNoRelation[] = $relatedModelData['foreign_key'];

            } else {
                dd($this->builder->getRelation($relation));
            }
        }

        $this->builder = $this->builder->select($selectsNoRelation);

        return $this->builder;

    }

}
