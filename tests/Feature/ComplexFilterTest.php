<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\ComplexFilterCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;
use Illuminate\Support\Collection;

class ComplexFilterTest extends TestCase
{
    private function filtered(array $tree): Collection
    {
        return (new ComplexFilterCriteria(User::query(), collect(['complexFilters' => $tree]), User::criteria()))
            ->apply()
            ->get();
    }

    public function test_and_logic(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'status' => 'active', 'age' => 30]);
        $this->makeUser(['first_name' => 'Bob', 'status' => 'active', 'age' => 10]);
        $this->makeUser(['first_name' => 'Carl', 'status' => 'inactive', 'age' => 30]);

        $results = $this->filtered([
            'logic' => 'and',
            'filters' => [
                ['column' => 'status', 'operator' => 'eq', 'value' => 'active'],
                ['column' => 'age', 'operator' => 'gte', 'value' => '18'],
            ],
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->first_name);
    }

    public function test_or_logic(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'status' => 'active']);
        $this->makeUser(['first_name' => 'Bob', 'status' => 'banned']);
        $this->makeUser(['first_name' => 'Carl', 'status' => 'inactive']);

        $results = $this->filtered([
            'logic' => 'or',
            'filters' => [
                ['column' => 'status', 'operator' => 'eq', 'value' => 'active'],
                ['column' => 'status', 'operator' => 'eq', 'value' => 'banned'],
            ],
        ]);

        $this->assertCount(2, $results);
    }

    public function test_nested_groups(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'status' => 'active', 'age' => 30]);
        $this->makeUser(['first_name' => 'Bob', 'status' => 'active', 'age' => 10]);
        $this->makeUser(['first_name' => 'Carl', 'status' => 'banned', 'age' => 40]);
        $this->makeUser(['first_name' => 'Dana', 'status' => 'banned', 'age' => 5]);

        // active AND age >= 18, OR banned AND age >= 18
        $results = $this->filtered([
            'logic' => 'or',
            'filters' => [
                [
                    'logic' => 'and',
                    'filters' => [
                        ['column' => 'status', 'operator' => 'eq', 'value' => 'active'],
                        ['column' => 'age', 'operator' => 'gte', 'value' => '18'],
                    ],
                ],
                [
                    'logic' => 'and',
                    'filters' => [
                        ['column' => 'status', 'operator' => 'eq', 'value' => 'banned'],
                        ['column' => 'age', 'operator' => 'gte', 'value' => '18'],
                    ],
                ],
            ],
        ]);

        $names = $results->pluck('first_name')->sort()->values()->all();
        $this->assertSame(['Alice', 'Carl'], $names);
    }
}
