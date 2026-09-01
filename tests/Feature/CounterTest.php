<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
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
}
