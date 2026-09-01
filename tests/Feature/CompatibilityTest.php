<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\ApplyCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\BaseCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\DefaultCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\ModelCriteriaContract;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;
use Illuminate\Http\Request;

/**
 * Exercises the exact public entry points external code in the monorepo
 * (`App\Core\AppBuilder`, `App\Core\Eloquent`) calls directly, to guard
 * against accidentally breaking them during the refactor.
 */
class CompatibilityTest extends TestCase
{
    public function test_default_criteria_allows_everything(): void
    {
        $criteria = new DefaultCriteria;

        $this->assertSame(['*'], $criteria->filterable());
        $this->assertSame(['*'], $criteria->orderable());
        $this->assertSame(['*'], $criteria->selectable());
        $this->assertSame(['*'], $criteria->relatable());
        $this->assertInstanceOf(ModelCriteriaContract::class, $criteria);
    }

    public function test_apply_criteria_static_entry_point_matches_historical_signature(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        // Same call shape as app/Core/AppBuilder.php: ApplyCriteria::applyCriteria($criteriaClass, $this, true, $filters)
        $builder = ApplyCriteria::applyCriteria(
            DefaultCriteria::class,
            User::query(),
            true,
            ['filters' => ['first_name:eq' => 'Alice']]
        );

        $this->assertCount(1, $builder->get());
    }

    public function test_apply_criteria_rejects_a_class_not_implementing_the_contract(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ApplyCriteria::applyCriteria(\stdClass::class, User::query());
    }

    public function test_base_criteria_static_column_helpers_are_unchanged(): void
    {
        $this->assertSame('full_name', BaseCriteria::columnNamePolicy('fullName'));
        $this->assertSame('a.b', BaseCriteria::dotRelations('a.b.c', true));
        $this->assertSame('c', BaseCriteria::getColumnFromDottedRelation('a.b.c'));
        $this->assertSame('`a`.`b`', BaseCriteria::escapeColumn('a.b'));
    }

    public function test_request_filter_trait_static_entry_points(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $request = Request::create('/?filters[first_name:eq]=Alice');
        app()->instance('request', $request);

        $results = User::applyCriteria(User::criteria())->get();

        $this->assertCount(1, $results);
    }

    public function test_request_filter_trait_get_filter_defs(): void
    {
        $this->makeUser();

        $defs = User::getFilterDefs();

        $this->assertSame(User::class, $defs['model']);
        $this->assertSame('users', $defs['table']);
        $this->assertArrayHasKey('filterable', $defs['allowed']);
        $this->assertArrayHasKey('orderable', $defs['allowed']);
        $this->assertArrayHasKey('selectable', $defs['allowed']);
        $this->assertArrayHasKey('relatable', $defs['allowed']);
        $this->assertNotEmpty($defs['columns']);
        $this->assertArrayHasKey('company', $defs['relations']->toArray());
    }

    public function test_request_filter_trait_sort_no_longer_throws(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();

        $request = Request::create('/');
        app()->instance('request', $request);

        // This was a broken method before the refactor (called an undefined
        // ApplyCriteria::sort()); it must now work.
        $results = User::sort()->get();

        $this->assertSame([$first->id, $second->id], $results->pluck('id')->all());
    }
}
