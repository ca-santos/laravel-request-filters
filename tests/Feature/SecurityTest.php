<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\ComplexFilterCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\OrderByCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_sql_injection_attempt_in_filter_value_is_treated_as_a_literal(): void
    {
        $this->makeUser(['first_name' => "Robert'); DROP TABLE users;--"]);
        $this->makeUser(['first_name' => 'Alice']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['first_name:eq' => "Robert'); DROP TABLE users;--"]]),
            User::criteria()
        ))->apply()->get();

        // The malicious value is matched as a literal string, table survives, no exception.
        $this->assertCount(1, $results);
        $this->assertSame(2, User::query()->count());
        $this->assertDatabaseHas('users', ['first_name' => 'Alice']);
    }

    public function test_sql_injection_attempt_in_complex_filter_value(): void
    {
        $this->makeUser(['first_name' => 'Alice']);

        $results = (new ComplexFilterCriteria(
            User::query(),
            collect(['complexFilters' => [
                'logic' => 'and',
                'filters' => [
                    ['column' => 'first_name', 'operator' => 'eq', 'value' => "' OR '1'='1"],
                ],
            ]]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(0, $results);
        $this->assertDatabaseHas('users', ['first_name' => 'Alice']);
    }

    public function test_like_wildcards_in_user_input_are_escaped(): void
    {
        $this->makeUser(['first_name' => '100%']);
        $this->makeUser(['first_name' => '100x']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['first_name:contains' => '100%']]),
            User::criteria()
        ))->apply()->get();

        // Without escaping, "100%" as a LIKE pattern would also match "100x".
        $this->assertCount(1, $results);
        $this->assertSame('100%', $results->first()->first_name);
    }

    public function test_like_underscore_wildcard_is_escaped(): void
    {
        $this->makeUser(['first_name' => 'a_b']);
        $this->makeUser(['first_name' => 'aXb']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['first_name:contains' => 'a_b']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('a_b', $results->first()->first_name);
    }

    public function test_disallowed_order_column_throws(): void
    {
        $criteria = \CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder::make()->setOrderable(['first_name']);

        $this->expectException(\InvalidArgumentException::class);

        (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'email']]), // not in the orderable() whitelist
            $criteria
        ))->apply();
    }

    public function test_invalid_relation_path_in_order_is_ignored_not_fatal(): void
    {
        $this->makeUser(['first_name' => 'Alice']);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'doesNotExist.column']]),
            User::criteria()
        ))->smartSort()->get();

        $this->assertCount(1, $results);
    }

    public function test_unsafe_order_column_name_is_dropped(): void
    {
        $this->makeUser();

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => [['column' => "id; DROP TABLE users;--", 'dir' => 'asc']]]),
            User::criteria()
        ))->smartSort()->get();

        $this->assertDatabaseHas('users', ['first_name' => 'John']);
        $this->assertCount(1, $results);
    }
}
