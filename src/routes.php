<?php

use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use CaueSantos\LaravelRequestFilters\Criteria\RequestFilterTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

if(!function_exists('laravelRequestFiltersDiscoveredLoadAll')){
    function laravelRequestFiltersDiscoveredLoadAll(): array
    {

        $models = Cache::get('laravel-request-filters-discovered');

        $modelsWithFilters = [];
        /**
         * @var RequestFilterTrait $name
         * @var  $item
         */
        foreach ($models['class'] as $name => $item) {
            if (isset($item['parent'][Model::class]) && isset($item['traits'][RequestFilterTrait::class])) {
                $modelsWithFilters[] = $name::getFilterDefs();
            }
        }

        return $modelsWithFilters;

    }
}

Route::get('/metadata', function () {

    return response([
        'data' => laravelRequestFiltersDiscoveredLoadAll()
    ]);

});

Route::get('/metadata/{entity}', function ($entity) {

    $values = array_values(array_filter(laravelRequestFiltersDiscoveredLoadAll(), function ($item) use ($entity) {
        return $item['table'] === $entity;
    }));

    return response([
        'data' => $values[0] ?? null
    ]);

});
