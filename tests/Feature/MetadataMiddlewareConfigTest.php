<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Tests\Fixtures\BlockingMiddleware;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

/**
 * `config('laravel-request-filters.metadata_middleware')` lets an application
 * layer its own middleware (auth, rate limiting, ...) onto the
 * `/filters/metadata` routes, on top of the built-in authorization check.
 */
class MetadataMiddlewareConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laravel-request-filters.metadata_middleware', [BlockingMiddleware::class]);
    }

    public function test_extra_middleware_from_config_is_applied_to_the_metadata_routes(): void
    {
        $response = $this->get('/filters/metadata');

        $response->assertStatus(418);
    }
}
