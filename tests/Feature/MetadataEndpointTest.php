<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Tests\TestCase;

/**
 * End-to-end check that the package boots cleanly (service provider,
 * discovery cache, route registration) and the metadata endpoint responds -
 * without depending on Brik or any application-specific class.
 */
class MetadataEndpointTest extends TestCase
{
    public function test_metadata_endpoint_lists_discovered_models(): void
    {
        $this->makeUser();

        $response = $this->get('/filters/metadata');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_metadata_endpoint_for_a_single_entity(): void
    {
        $this->makeUser();

        $response = $this->get('/filters/metadata/users');

        $response->assertOk();
    }
}
