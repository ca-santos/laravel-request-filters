<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters;

use CaueSantos\LaravelRequestFilters\Support\Values;

/**
 * @deprecated kept for backward compatibility (still used internally by the
 *             legacy `ResourceCriteria\*` engine) - use
 *             {@see \CaueSantos\LaravelRequestFilters\Support\Values} directly
 *             instead, which is the canonical implementation this now
 *             delegates to.
 */
class Helpers
{
    public static function convertValue($value)
    {
        return Values::convertValue($value);
    }

    public static function sanitizeValue(array|string|null $value): string|array|null
    {
        return Values::sanitizeValue($value);
    }
}
