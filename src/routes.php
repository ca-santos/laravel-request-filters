<?php

use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use CaueSantos\LaravelRequestFilters\Criteria\RequestFilterTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

if (!function_exists('laravelRequestFiltersDiscoveredLoadAll')) {
    function laravelRequestFiltersDiscoveredLoadAll(): array
    {

        $models = Cache::get('laravel-request-filters-discovered');

        if ($models === null) {
            // Cache miss (e.g. testing environment, where the service provider
            // deliberately doesn't populate it, or it simply hasn't run yet):
            // discover on demand instead of failing.
            $discovery = new AutoClassDiscovery();
            $discovery->discover(config('laravel-request-filters.models_folder'));
            $models = $discovery->getDiscovered();
        }

        $modelsWithFilters = [];
        /**
         * @var RequestFilterTrait $name
         * @var $item
         */
        foreach ($models['class'] ?? [] as $name => $item) {
            if (isset($item['parent'][Model::class]) && isset($item['traits'][RequestFilterTrait::class])) {
                $modelsWithFilters[] = $name::getFilterDefs();
            }
        }

        return $modelsWithFilters;

    }
}

Route::get('/metadata', function () {

    return response([
        'data' => laravelRequestFiltersDiscoveredLoadAll(),
    ]);

});

Route::get('/metadata/{entity}', function ($entity) {

    $values = array_values(array_filter(laravelRequestFiltersDiscoveredLoadAll(), function ($item) use ($entity) {
        return $item['table'] === $entity;
    }));

    return response([
        'data' => $values[0] ?? null,
    ]);

});
