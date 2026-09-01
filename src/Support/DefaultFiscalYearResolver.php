<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use CaueSantos\LaravelRequestFilters\Contracts\FiscalYearResolver;
use Carbon\Carbon;

/**
 * Default {@see FiscalYearResolver}.
 *
 * When the host application ships its own `App\Services\DateShortcutResolver`
 * (the resolver historically used by this package before this refactor), it
 * is used as-is so existing "financial year" behaviour is preserved without
 * requiring any change outside this package. When it isn't present (e.g. the
 * package is used standalone, or in this package's own test suite), a plain
 * calendar year (Jan 1 - Dec 31) is used as a conservative fallback.
 *
 * Applications that want a real fiscal year definition without relying on the
 * legacy class should bind their own {@see FiscalYearResolver} implementation
 * in their service container instead of relying on this default.
 */
final class DefaultFiscalYearResolver implements FiscalYearResolver
{
    private const LEGACY_RESOLVER = 'App\\Services\\DateShortcutResolver';

    public function resolve(string $shortcutKey): array
    {
        if (class_exists(self::LEGACY_RESOLVER) && method_exists(self::LEGACY_RESOLVER, 'resolve')) {
            /** @var array{from: Carbon, to: Carbon} $range */
            $range = [self::LEGACY_RESOLVER, 'resolve']($shortcutKey);

            return $range;
        }

        return $this->calendarYear($shortcutKey);
    }

    private function calendarYear(string $shortcutKey): array
    {
        $now = Carbon::now();

        $offset = match ($shortcutKey) {
            'last_financial_year' => -1,
            'next_financial_year' => 1,
            default => 0,
        };

        $from = $now->copy()->addYears($offset)->startOfYear();

        return ['from' => $from, 'to' => $from->copy()->addYear()];
    }
}
