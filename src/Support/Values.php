<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

/**
 * Canonical implementation of value conversion/sanitization used by the whole
 * engine. {@see \CaueSantos\LaravelRequestFilters\Helpers} keeps delegating
 * here for backward compatibility.
 */
final class Values
{
    /**
     * Convert a raw request value into the PHP type it most likely represents:
     * the literal strings "true"/"false" become booleans, plain numeric strings
     * become int/float (a leading zero followed by more digits is kept as a
     * string, so identifiers like "0123" are not mangled), everything else is
     * left untouched. Arrays are converted element-wise.
     */
    public static function convertValue(mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return array_map([self::class, 'convertValue'], $value);
        }

        if ($value === 'false' || $value === 'true') {
            return $value === 'true';
        }

        if (is_numeric($value)) {
            if (is_string($value) && str_starts_with($value, '0') && strlen($value) > 1) {
                return $value;
            }

            return $value + 0;
        }

        return $value;
    }

    /**
     * Strip characters that have no legitimate place in a filter value and could
     * otherwise be used to break out of a quoted SQL literal or a raw expression
     * (control characters, null bytes) and neutralise quote/backslash characters.
     *
     * This is a defence-in-depth measure: the engine should always prefer query
     * bindings over interpolation, but a handful of code paths still have to
     * build raw SQL fragments (e.g. dynamically built CONCAT() expressions), so
     * values that end up there are sanitised first.
     */
    public static function sanitizeValue(array|string|null $value): string|array|null
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeValue'], $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "\'", $value);
        $value = str_replace('"', '\"', $value);
        $value = str_replace("\0", '', $value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
    }

    /**
     * Escape the SQL LIKE wildcards (`%`, `_`) and the escape character itself
     * so a user-supplied "contains"/"starts"/"ends" value is matched literally
     * instead of being interpreted as a pattern. Pair with a bound `ESCAPE ?`
     * clause using {@see self::LIKE_ESCAPE_CHAR}.
     */
    public static function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** Escape character bound alongside every LIKE clause the engine builds. */
    public const LIKE_ESCAPE_CHAR = '\\';
}
