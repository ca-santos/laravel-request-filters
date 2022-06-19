<?php

namespace CaueSantos\LaravelRequestFilters\Tests;

use CaueSantos\LaravelRequestFilters\Facades\LaravelRequestFilters;
use CaueSantos\LaravelRequestFilters\ServiceProvider;
use Orchestra\Testbench\TestCase;

class LaravelRequestFiltersTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'laravel-request-filters' => LaravelRequestFilters::class,
        ];
    }

    public function testExample()
    {
        $this->assertEquals(1, 1);
    }
}
