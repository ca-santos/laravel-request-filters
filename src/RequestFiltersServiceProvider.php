<?php

namespace CaueSantos\LaravelRequestFilters;

use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class RequestFiltersServiceProvider extends RouteServiceProvider
{

    const CONFIG_PATH = __DIR__ . '/../config/laravel-request-filters.php';

    public function boot()
    {

        if ($this->app->runningInConsole()) {

            $this->publishes([
                self::CONFIG_PATH => config_path('laravel-request-filters.php'),
            ], 'config');

        }

        $modelsFolder = config('laravel-request-filters.models_folder');
        $makeCache = function () use ($modelsFolder) {
            AutoClassDiscovery::discover($modelsFolder);
            Cache::forever('laravel-request-filters-discovered', AutoClassDiscovery::getDiscovered());
        };

        //SET CACHE
        if (app('env') === 'production') {
            if (Cache::get('laravel-request-filters-discovered') === null) {
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
            'laravel-request-filters'
        );

        $this->app->bind('laravel-request-filters', function () {
            return new LaravelRequestFilters();
        });
    }

}
