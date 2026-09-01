<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters;

use CaueSantos\AutoClassDiscovery\AutoClassDiscovery;
use CaueSantos\LaravelRequestFilters\Contracts\FiscalYearResolver;
use CaueSantos\LaravelRequestFilters\Http\Middleware\Authorize;
use CaueSantos\LaravelRequestFilters\Support\DefaultFiscalYearResolver;
use Closure;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class RequestFiltersServiceProvider extends RouteServiceProvider
{
    const CONFIG_PATH = __DIR__.'/../config/laravel-request-filters.php';

    /** @var (Closure(Request): bool)|null */
    protected static $authUsing;

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
            ->middleware([Authorize::class, ...config('laravel-request-filters.metadata_middleware', [])])
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

    /**
     * Register the callback used to authorize requests to the `/filters/metadata`
     * routes - they describe a model's real columns, relations, and attributes.
     * Pass `null` to restore the default (see {@see self::defaultAuthorization()}).
     *
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function auth(?Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /** Whether `$request` is authorized to view the `/filters/metadata` routes. */
    public static function check(Request $request): bool
    {
        return (bool) (static::$authUsing ?? static::defaultAuthorization())($request);
    }

    /**
     * Only local/testing environments are authorized out of the box - call
     * {@see self::auth()} from your own service provider to allow it
     * elsewhere (e.g. behind your own admin/permission check).
     */
    protected static function defaultAuthorization(): Closure
    {
        return static fn () => app()->environment('local', 'testing');
    }
}
