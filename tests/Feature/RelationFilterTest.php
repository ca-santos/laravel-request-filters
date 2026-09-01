<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Post;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class RelationFilterTest extends TestCase
{
    public function test_belongs_to_relation_filter(): void
    {
        $company = $this->makeCompany(['name' => 'Acme']);
        $other = $this->makeCompany(['name' => 'Globex']);
        $this->makeUser(['first_name' => 'Alice', 'company_id' => $company->id]);
        $this->makeUser(['first_name' => 'Bob', 'company_id' => $other->id]);

        $filtered = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['company.name:eq' => 'Acme']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $filtered);
        $this->assertSame('Alice', $filtered->first()->first_name);
    }

    public function test_has_many_relation_filter(): void
    {
        $alice = $this->makeUser(['first_name' => 'Alice']);
        $bob = $this->makeUser(['first_name' => 'Bob']);
        $this->makePost($alice, ['title' => 'Special post']);
        $this->makePost($bob, ['title' => 'Ordinary post']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts.title:contains' => 'Special']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results->first()->first_name);
    }

    public function test_belongs_to_many_relation_filter(): void
    {
        $alice = $this->makeUser(['first_name' => 'Alice']);
        $bob = $this->makeUser(['first_name' => 'Bob']);
        $postA = $this->makePost($alice);
        $postB = $this->makePost($bob);
        $php = $this->makeTag(['name' => 'php']);
        $go = $this->makeTag(['name' => 'go']);
        $postA->tags()->attach($php);
        $postB->tags()->attach($go);

        $results = (new FilterCriteria(
            Post::query(),
            collect(['filters' => ['tags.name:eq' => 'php']]),
            Post::query()->getModel()->criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame($postA->id, $results->first()->id);
    }

    public function test_nested_relation_filter(): void
    {
        $company = $this->makeCompany();
        $alice = $this->makeUser(['first_name' => 'Alice', 'company_id' => $company->id]);
        $this->makePost($alice, ['title' => 'Deep nested match']);

        $results = (new FilterCriteria(
            \CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company::query(),
            collect(['filters' => ['users.posts.title:contains' => 'Deep nested']]),
            (new \CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company)->criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame($company->id, $results->first()->id);
    }

    public function test_invalid_relation_is_silently_skipped(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['doesNotExist.title:eq' => 'x']]),
            User::criteria()
        ))->apply()->get();

        // No exception, and the (unresolvable) condition has no effect - all rows still returned.
        $this->assertCount(2, $results);
    }

    public function test_empty_relation_filter(): void
    {
        $alice = $this->makeUser(['first_name' => 'Alice']);
        $bob = $this->makeUser(['first_name' => 'Bob']);
        $this->makePost($alice);

        $withPosts = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts.title:!empty' => '1']]),
            User::criteria()
        ))->apply()->get();

        $withoutPosts = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts.title:empty' => '1']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $withPosts);
        $this->assertSame('Alice', $withPosts->first()->first_name);
        $this->assertCount(1, $withoutPosts);
        $this->assertSame('Bob', $withoutPosts->first()->first_name);
    }
}
