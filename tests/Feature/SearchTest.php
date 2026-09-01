<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\ApplyCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder;
use CaueSantos\LaravelRequestFilters\Criteria\DefaultCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\SearchCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;
use Illuminate\Http\Request;

class SearchTest extends TestCase
{
    public function test_search_matches_any_searchable_field(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'last_name' => 'Wonderland', 'email' => 'alice@example.com']);
        $this->makeUser(['first_name' => 'Bob', 'last_name' => 'Wonder', 'email' => 'bob@example.com']);
        $this->makeUser(['first_name' => 'Carl', 'last_name' => 'Doe', 'email' => 'carl@example.com']);

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => 'wonder']),
            User::criteria()
        ))->apply()->get();

        $names = $results->pluck('first_name')->sort()->values()->all();
        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function test_search_matches_across_a_relation(): void
    {
        $acme = $this->makeCompany(['name' => 'Acme Corp']);
        $globex = $this->makeCompany(['name' => 'Globex']);
        $this->makeUser(['first_name' => 'Alice', 'company_id' => $acme->id]);
        $this->makeUser(['first_name' => 'Bob', 'company_id' => $globex->id]);

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => 'Acme']),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->first_name);
    }

    public function test_empty_search_term_has_no_effect(): void
    {
        $this->makeUser();
        $this->makeUser();

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => '   ']),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(2, $results);
    }

    public function test_search_has_no_effect_without_a_searchable_declaration(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => 'Alice']),
            DefaultCriteria::class
        ))->apply()->get();

        // DefaultCriteria doesn't implement SearchableModelCriteria - `q` is a no-op.
        $this->assertCount(2, $results);
    }

    public function test_search_has_no_effect_when_declared_but_empty(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => 'Alice']),
            CriteriaBuilder::make() // searchable() defaults to []
        ))->apply()->get();

        $this->assertCount(2, $results);
    }

    public function test_search_is_combined_with_filters_and_complex_filters_via_apply_criteria(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'last_name' => 'Wonderland', 'status' => 'active', 'age' => 30]);
        $this->makeUser(['first_name' => 'Alicia', 'last_name' => 'Keys', 'status' => 'active', 'age' => 17]);
        $this->makeUser(['first_name' => 'Bob', 'last_name' => 'Wonder', 'status' => 'banned', 'age' => 30]);

        $request = Request::create('/');
        $request->query->add([
            'q' => 'ali',
            'filters' => ['status:eq' => 'active'],
            'complexFilters' => [
                'logic' => 'and',
                'filters' => [
                    ['column' => 'age', 'operator' => 'gte', 'value' => '18'],
                ],
            ],
        ]);
        app()->instance('request', $request);

        $results = ApplyCriteria::applyCriteria(User::criteria(), User::query())->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->first_name);
    }

    public function test_search_matches_a_computed_field(): void
    {
        $this->makeUser(['first_name' => 'John', 'last_name' => 'Smith']);
        $this->makeUser(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $results = (new SearchCriteria(
            User::query(),
            collect(['q' => 'John Smith']),
            CriteriaBuilder::make()
                ->setSearchable(['full_name'])
                ->computed('full_name', fn ($query) => \CaueSantos\LaravelRequestFilters\Support\ColumnResolver::concat($query, ['first_name', 'last_name']))
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('John', $results->first()->first_name);
    }
}
