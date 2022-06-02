<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Http\Request;
use Schema;

trait RequestFilterTrait
{

    protected static ?Request $request;

    public function newEloquentBuilder($builder): Builder
    {
        return new RequestFilterBuilder($builder);
    }

    /**
     * @param class-string<ModelCriteriaContract> $modelCriteria
     * @return Builder
     * @throws Exception
     */
    public static function applyCriteria(string $modelCriteria): Builder
    {
        return ApplyCriteria::applyCriteria($modelCriteria, static::query());
    }

    public static function sort(): Builder
    {
        return ApplyCriteria::sort(static::query());
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public static function getFilterDefs(): array
    {

        /**
         * @var Model $model
         */
        $model = static::query()->getModel();

        $criteriaClass = self::criteria();

        if (!class_exists($criteriaClass))
            throw new Exception($criteriaClass . ' is not a valid criteria class');

        /**
         * @var ModelCriteriaContract $modelCriteria
         */
        $modelCriteria = new $criteriaClass();

        $columnsMap = array_map(function ($item) {
            return [
                'field' => $item->getName(),
                'type' => $item->getType()->getName(),
                'length' => $item->getLength(),
                'default' => $item->getDefault(),
                'fixed' => $item->getFixed(),
            ];
        }, Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns($model->getTable()));

        return [
            'model' => get_class($model),
            'table' => $model->getTable(),
            'fillable' => [$model->getKeyName(), ...$model->getFillable()],
            'attributes' => $model->getAttributes(),
            'columns' => array_values($columnsMap),
            'allowed' => [
                'filterable' => $modelCriteria->filterable(),
                'orderable' => $modelCriteria->orderable(),
                'selectable' => $modelCriteria->selectable()
            ]
        ];

    }

    /**
     * @return class-string<Model>
     */
    public abstract static function criteria(): string;

}

