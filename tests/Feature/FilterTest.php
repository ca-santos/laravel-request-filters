<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class FilterTest extends TestCase
{
    private function filtered(array $filters): \Illuminate\Support\Collection
    {
        return (new FilterCriteria(User::query(), collect(['filters' => $filters]), User::criteria()))
            ->apply()
            ->get();
    }

    public function test_equality(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'email' => 'alice@example.com']);
        $this->makeUser(['first_name' => 'Bob', 'email' => 'bob@example.com']);

        $results = $this->filtered(['first_name:eq' => 'Alice']);

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->first_name);
    }

    public function test_inequality(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $results = $this->filtered(['first_name:!eq' => 'Alice']);

        $this->assertCount(1, $results);
        $this->assertSame('Bob', $results->first()->first_name);
    }

    public function test_comparisons(): void
    {
        $this->makeUser(['first_name' => 'Young', 'age' => 10]);
        $this->makeUser(['first_name' => 'Mid', 'age' => 25]);
        $this->makeUser(['first_name' => 'Old', 'age' => 60]);

        $this->assertCount(2, $this->filtered(['age:gte' => '25']));
        $this->assertCount(1, $this->filtered(['age:gt' => '25']));
        $this->assertCount(2, $this->filtered(['age:lte' => '25']));
        $this->assertCount(1, $this->filtered(['age:lt' => '25']));
    }

    public function test_contains(): void
    {
        $this->makeUser(['email' => 'alice@example.com']);
        $this->makeUser(['email' => 'bob@other.com']);

        $results = $this->filtered(['email:contains' => 'example']);

        $this->assertCount(1, $results);
        $this->assertSame('alice@example.com', $results->first()->email);
    }

    public function test_starts_and_ends(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Alicia']);
        $this->makeUser(['first_name' => 'Malice']);

        $this->assertCount(2, $this->filtered(['first_name:starts' => 'Ali']));
        $this->assertCount(2, $this->filtered(['first_name:ends' => 'ice']));
    }

    public function test_in_and_not_in(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);
        $this->makeUser(['first_name' => 'Carl']);

        $this->assertCount(2, $this->filtered(['first_name:in' => 'Alice,Bob']));
        $this->assertCount(1, $this->filtered(['first_name:!in' => 'Alice,Bob']));
    }

    public function test_empty_and_not_empty(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'age' => null]);
        $this->makeUser(['first_name' => 'Bob', 'age' => 20]);

        $this->assertCount(1, $this->filtered(['age:empty' => '1']));
        $this->assertCount(1, $this->filtered(['age:!empty' => '1']));
    }

    public function test_between(): void
    {
        $this->makeUser(['first_name' => 'Young', 'age' => 10]);
        $this->makeUser(['first_name' => 'Mid', 'age' => 25]);
        $this->makeUser(['first_name' => 'Old', 'age' => 60]);

        $this->assertCount(1, $this->filtered(['age:between' => '20,30']));
        $this->assertCount(2, $this->filtered(['age:!between' => '20,30']));
    }

    public function test_disallowed_field_is_silently_dropped(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $criteria = \CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder::make()->setFilterable(['first_name']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['first_name:eq' => 'Alice', 'email:eq' => 'nope@nowhere.com']]),
            $criteria
        ))->apply()->get();

        // The disallowed "email" condition is dropped, only the allowed "first_name" one applies.
        $this->assertCount(1, $results);
    }

    public function test_custom_filter_takes_over(): void
    {
        $this->makeUser(['first_name' => 'Kid', 'age' => 10]);
        $this->makeUser(['first_name' => 'Adult', 'age' => 30]);

        $results = $this->filtered(['is_adult:eq' => '1']);

        $this->assertCount(1, $results);
        $this->assertSame('Adult', $results->first()->first_name);
    }

    public function test_alias_resolution(): void
    {
        $this->makeUser(['email' => 'alice@example.com']);
        $this->makeUser(['email' => 'bob@other.com']);

        $results = $this->filtered(['email_address:eq' => 'alice@example.com']);

        $this->assertCount(1, $results);
    }
}
