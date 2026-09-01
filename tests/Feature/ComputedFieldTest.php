<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\OrderByCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class ComputedFieldTest extends TestCase
{
    public function test_filter_by_computed_field(): void
    {
        $this->makeUser(['first_name' => 'John', 'last_name' => 'Smith']);
        $this->makeUser(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['full_name:contains' => 'John Smith']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('John', $results->first()->first_name);
    }

    public function test_sort_by_computed_field(): void
    {
        $this->makeUser(['first_name' => 'Zack', 'last_name' => 'Aardvark']);
        $this->makeUser(['first_name' => 'Amy', 'last_name' => 'Zebra']);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'full_name']]),
            User::criteria()
        ))->apply()->get();

        // "Amy Zebra" < "Zack Aardvark" alphabetically on the full concatenated string.
        $this->assertSame('Amy', $results->first()->first_name);
    }

    public function test_computed_field_never_compares_the_bare_alias(): void
    {
        // Regression guard: filtering by a computed field must not attempt to
        // reference a SELECT alias in WHERE (invalid SQL) - it must succeed.
        $this->makeUser(['first_name' => 'A', 'last_name' => 'B']);

        $this->expectNotToPerformAssertions();

        (new FilterCriteria(
            User::query(),
            collect(['filters' => ['full_name:eq' => 'A B']]),
            User::criteria()
        ))->apply()->get();
    }
}
