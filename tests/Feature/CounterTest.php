<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Criteria\OrderByCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class CounterTest extends TestCase
{
    public function test_count_comparison(): void
    {
        $prolific = $this->makeUser(['first_name' => 'Prolific']);
        $quiet = $this->makeUser(['first_name' => 'Quiet']);

        $this->makePost($prolific);
        $this->makePost($prolific);
        $this->makePost($prolific);
        $this->makePost($quiet);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts_count:gte' => '2']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Prolific', $results->first()->first_name);
    }

    public function test_filtered_count(): void
    {
        $user = $this->makeUser(['first_name' => 'Author']);

        $this->makePost($user, ['published_at' => now()]);
        $this->makePost($user, ['published_at' => now()]);
        $this->makePost($user, ['published_at' => null]);

        $atLeastTwoPublished = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['published_posts_count:gte' => '2']]),
            User::criteria()
        ))->apply()->get();

        $atLeastThreeTotal = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts_count:gte' => '3']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $atLeastTwoPublished);
        $this->assertCount(1, $atLeastThreeTotal);
    }

    public function test_count_between(): void
    {
        $user = $this->makeUser();
        $this->makePost($user);
        $this->makePost($user);

        $results = (new FilterCriteria(
            User::query(),
            collect(['filters' => ['posts_count:between' => '1,5']]),
            User::criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
    }

    public function test_sort_by_counter_field(): void
    {
        $quiet = $this->makeUser(['first_name' => 'Quiet']);
        $prolific = $this->makeUser(['first_name' => 'Prolific']);

        $this->makePost($prolific);
        $this->makePost($prolific);
        $this->makePost($prolific);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['desc' => 'posts_count']]),
            User::criteria()
        ))->apply()->get();

        $this->assertSame('Prolific', $results->first()->first_name);
        $this->assertSame(3, (int) $results->first()->posts_count);
    }

    public function test_sort_by_constrained_counter_field(): void
    {
        $withoutPublished = $this->makeUser(['first_name' => 'A']);
        $withPublished = $this->makeUser(['first_name' => 'B']);

        $this->makePost($withoutPublished);
        $this->makePost($withPublished, ['published_at' => now()]);
        $this->makePost($withPublished, ['published_at' => now()]);

        $results = (new OrderByCriteria(
            User::query(),
            collect(['order' => ['desc' => 'published_posts_count']]),
            User::criteria()
        ))->apply()->get();

        $this->assertSame('B', $results->first()->first_name);
    }

    public function test_sort_by_counter_does_not_duplicate_an_alias_already_selected_by_count_param(): void
    {
        // Regression guard: `count=posts` (CountCriteria) already selects a real
        // `posts_count` column via withCount()'s default alias; sorting by the
        // `posts_count` counter afterwards must reuse it instead of adding a
        // second, conflicting subselect under the same alias.
        $quiet = $this->makeUser(['first_name' => 'Quiet']);
        $prolific = $this->makeUser(['first_name' => 'Prolific']);
        $this->makePost($prolific);
        $this->makePost($prolific);

        $results = (new OrderByCriteria(
            User::query()->withCount('posts'),
            collect(['order' => ['desc' => 'posts_count']]),
            User::criteria()
        ))->apply()->get();

        $this->assertSame('Prolific', $results->first()->first_name);
    }
}
