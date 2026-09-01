<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters;

use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use CaueSantos\LaravelRequestFilters\Contracts\FiscalYearResolver;
use CaueSantos\LaravelRequestFilters\Support\DefaultFiscalYearResolver;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class RequestFiltersServiceProvider extends RouteServiceProvider
{
    const CONFIG_PATH = __DIR__.'/../config/laravel-request-filters.php';

    public function boot()
    {

        if ($this->app->runningInConsole()) {

            $this->publishes([
                self::CONFIG_PATH => config_path('laravel-request-filters.php'),
            ], 'config');

        }

        $modelsFolder = config('laravel-request-filters.models_folder');
        $makeCache = function () use ($modelsFolder) {
            $discovery = new AutoClassDiscovery();
            $discovery->discover($modelsFolder);
            Cache::forever('laravel-request-filters-discovered', $discovery->getDiscovered());
        };

        // SET CACHE (skip in testing environment or if cache not available)
        if ($this->app->environment('testing') || $this->app->runningUnitTests()) {
            // Skip cache during testing - discover on demand
        } elseif ($this->app->environment('production')) {
            try {
                if (Cache::get('laravel-request-filters-discovered') === null) {
                    $makeCache();
                }
            } catch (\Exception $e) {
                // Cache not available, skip
            }
        } else {
            try {
                $makeCache();
            } catch (\Exception $e) {
                // Cache not available, skip
            }
        }

        Route::prefix('filters')
            ->group(__DIR__.DIRECTORY_SEPARATOR.'routes.php');

    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'laravel-request-filters'
        );

        $this->app->bind('laravel-request-filters', function () {
            return new LaravelRequestFilters;
        });

        // Bind a default fiscal-year resolver so `date_this_financial_year` and
        // friends work out of the box. Applications with their own definition
        // of "fiscal year" should rebind this interface in their own service
        // provider rather than relying on the package default.
        $this->app->bindIf(FiscalYearResolver::class, DefaultFiscalYearResolver::class);
    }
}
