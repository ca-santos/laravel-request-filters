<?php

namespace CaueSantos\LaravelRequestFilters;

use Cache;
use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

class ServiceProvider extends RouteServiceProvider
{

    const CONFIG_PATH = __DIR__ . '/../config/laravel-query-filters.php';

    public function boot()
    {

        $this->publishes([
            self::CONFIG_PATH => config_path('laravel-query-filters.php'),
        ], 'config');

        $modelsFolder = config('laravel-query-filters.models_folder');
        $makeCache = function () use ($modelsFolder) {
            AutoClassDiscovery::discover($modelsFolder);
            Cache::forever('laravel-query-filters-discovered', AutoClassDiscovery::getDiscovered());
        };

        //SET CACHE
        if (app('env') === 'production') {
            if (Cache::get('laravel-query-filters-discovered') === null) {
                $makeCache();
            }
        } else {
            $makeCache();
        }

        Route
            ::prefix('filters')
            ->group(__DIR__ . DIRECTORY_SEPARATOR . 'routes.php');

    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'laravel-query-filters'
        );

        $this->app->bind('laravel-query-filters', function () {
            return new LaravelRequestFilters();
        });
    }

}
