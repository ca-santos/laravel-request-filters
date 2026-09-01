<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Contracts;

use Carbon\Carbon;

/**
 * Resolves the boundaries of a "financial year" date shortcut. The concept of
 * a fiscal year is application-specific (it depends on the company/tenant),
 * so this package only depends on this interface - never on a concrete
 * application class - keeping it usable standalone.
 *
 * Bind your own implementation in your application's service provider to
 * override the package default:
 *
 *   $this->app->bind(FiscalYearResolver::class, MyFiscalYearResolver::class);
 */
interface FiscalYearResolver
{
    /**
     * @param  string  $shortcutKey  one of `this_financial_year`, `last_financial_year`, `next_financial_year`
     * @return array{from: Carbon, to: Carbon} half-open range: `to` is exclusive
     */
    public function resolve(string $shortcutKey): array;
}
