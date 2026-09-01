<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\FilterCriteria;
use CaueSantos\LaravelRequestFilters\Support\DateShortcuts;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Post;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

class DateShortcutTest extends TestCase
{
    public function test_today_shortcut_via_local_scope(): void
    {
        $user = $this->makeUser();
        $this->makePost($user, ['title' => 'Today', 'published_at' => Carbon::now()]);
        $this->makePost($user, ['title' => 'Yesterday', 'published_at' => Carbon::yesterday()->setTime(12, 0)]);

        $results = Post::whereDateIsToday('published_at')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Today', $results->first()->title);
    }

    public function test_today_shortcut_via_generic_filter_operator(): void
    {
        $user = $this->makeUser();
        $this->makePost($user, ['title' => 'Today', 'published_at' => Carbon::now()]);
        $this->makePost($user, ['title' => 'Yesterday', 'published_at' => Carbon::yesterday()->setTime(12, 0)]);

        $results = (new FilterCriteria(
            Post::query(),
            collect(['filters' => ['published_at:date_today' => '1']]),
            (new Post)->criteria()
        ))->apply()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Today', $results->first()->title);
    }

    #[DataProvider('halfOpenBoundaryShortcuts')]
    public function test_half_open_boundary_excludes_the_first_instant_of_the_next_period(string $key): void
    {
        $range = DateShortcuts::range($key, 3);

        // The boundary instant itself must NOT be contained (half-open [from, to)).
        $this->assertFalse($range->contains($range->to->copy()));
        $this->assertTrue($range->contains($range->to->copy()->subSecond()));
        $this->assertTrue($range->contains($range->from->copy()));
    }

    public static function halfOpenBoundaryShortcuts(): array
    {
        return [
            ['this_week'], ['last_week'], ['next_week'],
            ['this_month'], ['last_month'], ['next_month'],
            ['this_quarter'], ['this_year'],
        ];
    }

    public function test_closed_rolling_window_shortcuts_are_consistent_between_sql_and_in_memory(): void
    {
        $inRange = Carbon::now()->subDays(2);
        $outOfRange = Carbon::now()->subDays(10);

        $this->assertTrue(DateShortcuts::contains('last_n_days', $inRange, 5));
        $this->assertFalse(DateShortcuts::contains('last_n_days', $outOfRange, 5));
    }

    public function test_fiscal_year_shortcut_resolves_without_an_external_dependency(): void
    {
        $range = DateShortcuts::range('this_financial_year');

        $this->assertTrue($range->from->lessThan($range->to));
    }

    public function test_in_memory_evaluator_uses_the_same_date_shortcut_semantics(): void
    {
        $evaluator = new \CaueSantos\LaravelRequestFilters\Criteria\ComplexFilterCriteriaToCode;

        $todayItem = (object) ['published_at' => Carbon::now()->toDateTimeString()];
        $yesterdayItem = (object) ['published_at' => Carbon::yesterday()->setTime(12, 0)->toDateTimeString()];

        $result = $evaluator->apply(collect([$todayItem, $yesterdayItem]), [
            'logic' => 'and',
            'filters' => [
                ['attribute' => 'published_at', 'operator' => 'date_today', 'value' => null],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($todayItem, $result->first());
    }
}
