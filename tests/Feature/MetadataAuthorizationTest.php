<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\RequestFiltersServiceProvider;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

/**
 * The /filters/metadata routes describe a model's real columns, relations,
 * and attributes - not something every application wants exposed to any
 * caller by default. Covers the built-in local/testing-only default and the
 * {@see RequestFiltersServiceProvider::auth()} override.
 */
class MetadataAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestFiltersServiceProvider::auth(null);

        parent::tearDown();
    }

    public function test_metadata_route_is_allowed_in_testing_by_default(): void
    {
        $response = $this->get('/filters/metadata');

        $response->assertOk();
    }

    public function test_metadata_route_is_forbidden_outside_local_and_testing_by_default(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/filters/metadata');

        $response->assertForbidden();
    }

    public function test_custom_auth_callback_can_authorize_a_normally_forbidden_environment(): void
    {
        $this->app['env'] = 'production';
        RequestFiltersServiceProvider::auth(fn () => true);

        $response = $this->get('/filters/metadata');

        $response->assertOk();
    }

    public function test_custom_auth_callback_can_deny_a_normally_allowed_environment(): void
    {
        RequestFiltersServiceProvider::auth(fn () => false);

        $response = $this->get('/filters/metadata');

        $response->assertForbidden();
    }

    public function test_custom_auth_callback_receives_the_request(): void
    {
        RequestFiltersServiceProvider::auth(fn ($request) => $request->query('token') === 'secret');

        $this->get('/filters/metadata')->assertForbidden();
        $this->get('/filters/metadata?token=secret')->assertOk();
    }
}
