<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\OrderByCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Post;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class SortTest extends TestCase
{
    public function test_simple_sort(): void
    {
        $this->makeUser(['first_name' => 'Bob']);
        $this->makeUser(['first_name' => 'Alice']);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'first_name']]),
            User::criteria()
        ))->apply()->get();

        $this->assertSame(['Alice', 'Bob'], $results->pluck('first_name')->all());
    }

    public function test_sort_by_relation_column(): void
    {
        $companyA = $this->makeCompany(['name' => 'Alpha Co']);
        $companyB = $this->makeCompany(['name' => 'Zeta Co']);
        $this->makeUser(['first_name' => 'Works at Zeta', 'company_id' => $companyB->id]);
        $this->makeUser(['first_name' => 'Works at Alpha', 'company_id' => $companyA->id]);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'company.name']]),
            User::criteria()
        ))->smartSort();

        $names = $results->get()->pluck('first_name')->all();

        $this->assertSame(['Works at Alpha', 'Works at Zeta'], $names);
    }

    public function test_sort_by_nested_relation_column(): void
    {
        $company = $this->makeCompany();
        $userA = $this->makeUser(['first_name' => 'A', 'company_id' => $company->id]);
        $userB = $this->makeUser(['first_name' => 'B', 'company_id' => $company->id]);
        $this->makePost($userA, ['title' => 'Zzz post']);
        $this->makePost($userB, ['title' => 'Aaa post']);

        $results = (new OrderByCriteria(
            Company::query(),
            collect(['order' => ['asc' => 'users.posts.title']]),
            (new Company)->criteria()
        ))->smartSort()->get();

        $this->assertCount(1, $results); // one company, but this exercises the join without throwing
    }

    public function test_sort_by_many_to_many_relation(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $postA = $this->makePost($userA, ['title' => 'Post A']);
        $postB = $this->makePost($userB, ['title' => 'Post B']);
        $postA->tags()->attach($this->makeTag(['name' => 'zzz-tag']));
        $postB->tags()->attach($this->makeTag(['name' => 'aaa-tag']));

        $results = (new OrderByCriteria(
            Post::query(),
            collect(['order' => ['asc' => 'tags.name']]),
            (new Post)->criteria()
        ))->smartSort()->get();

        $this->assertSame(['Post B', 'Post A'], $results->pluck('title')->all());
    }

    public function test_fallback_to_default_sort_column(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => []]),
            User::criteria()
        ))->smartSort()->get();

        $this->assertSame([$first->id, $second->id], $results->pluck('id')->all());
    }

    public function test_custom_sort(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'last_name' => 'Zeta']);
        $this->makeUser(['first_name' => 'Bob', 'last_name' => 'Alpha']);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['asc' => 'full_name_reversed']]),
            User::criteria()
        ))->smartSort()->get();

        $this->assertSame('Bob', $results->first()->first_name);
    }
}
