<?php

namespace CaueSantos\LaravelRequestFilters\Criteria;

use App\Core\Eloquent;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

class ApplyCriteria
{

    /**
     * @param $modelCriteria
     * @param Builder $builder
     * @param bool $skipOrder
     * @return Builder
     * @throws Exception
     */
    public static function applyCriteria($modelCriteria, Builder $builder, bool $skipOrder = false): Builder
    {
        if (in_array(ModelCriteriaContract::class, class_implements($modelCriteria)) === false) {
            throw new Exception($modelCriteria . ' doesn\'t implements ' . ModelCriteriaContract::class);
        }

        $request = request();
        $query = $request->query();

        if (isset($query['filter'])) {
            $builder = (new FilterCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['select'])) {
            $builder = (new SelectCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['count'])) {
            $builder = (new CountCriteria($builder, $request, $modelCriteria))->apply();
        }

        if (isset($query['order']) && !$skipOrder) {
            $builder = (new OrderByCriteria($builder, $request, $modelCriteria))->apply();
        }

        return $builder;

    }

    /**
     * @param $modelCriteria
     * @param Builder $builder
     * @param array $options
     * @return array
     * @throws BindingResolutionException
     * @throws Exception
     */
    public static function smartPagination($modelCriteria, Builder $builder, array $options = []): array
    {

        $builder = self::applyCriteria($modelCriteria, $builder, true);
        $model = $builder->getModel();

        $page = Paginator::resolveCurrentPage();
        $perPage = $options['per_page'] ?? request()->query('per_page', 30);

        $orderCriteria = new OrderByCriteria($builder, request(), $modelCriteria);
        $smartSort = $orderCriteria->smartSort($options);
        $builder = $smartSort[0];

        $results = ($total = $builder->toBase()->getCountForPagination())
            ? $builder->forPage($page, $perPage)->get('*')
            : $builder->getModel()->newCollection();

        $results = $orderCriteria::collectionSort($results, $smartSort[1]);
        $results = $orderCriteria::collectionSort($results, $smartSort[2], 'DESC');

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => array_values($results->toArray()),
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        ])->toArray();

    }

}
