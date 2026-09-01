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

    private const BOOLEAN_TYPES = ['bool', 'boolean'];

    private const INTEGER_TYPES = ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'serial', 'bigserial', 'smallserial', 'year'];

    private const FLOAT_TYPES = ['float', 'double', 'decimal', 'numeric', 'real'];

    private const TEXT_TYPES = ['char', 'varchar', 'string', 'text', 'enum', 'uuid', 'guid', 'json', 'jsonb', 'binary', 'blob'];

    /**
     * Convert a raw request value according to the database column's real
     * type (from {@see \CaueSantos\LaravelRequestFilters\Support\SchemaIntrospector}),
     * instead of guessing from the value's own shape like {@see self::convertValue()}
     * does - this is what tells a numeric-*looking* value in a text column
     * (a status code "42", a zero-padded reference "0123") apart from an
     * actually numeric column, instead of coercing both the same way.
     *
     * Falls back to {@see self::convertValue()} whenever `$type` is null, not
     * one of the buckets below (dates are intentionally left alone here -
     * see {@see \CaueSantos\LaravelRequestFilters\Criteria\BaseFilterCriteria::applyComparison()}),
     * or the value doesn't actually look like that bucket's shape (e.g. the
     * literal word "true" against an integer column) - an exotic/undetected
     * column type, or an unusual value, keeps behaving exactly like it always
     * has rather than being forced into an invalid cast.
     */
    public static function castForColumnType(mixed $value, ?string $type): mixed
    {
        if (is_array($value)) {
            return array_map(static fn ($v) => self::castForColumnType($v, $type), $value);
        }

        if ($type === null || !is_string($value)) {
            return self::convertValue($value);
        }

        return match (true) {
            self::matchesType($type, self::BOOLEAN_TYPES) => self::toBoolean($value),
            self::matchesType($type, self::INTEGER_TYPES) => is_numeric($value) ? $value + 0 : self::convertValue($value),
            self::matchesType($type, self::FLOAT_TYPES) => is_numeric($value) ? (float) $value : self::convertValue($value),
            self::matchesType($type, self::TEXT_TYPES) => $value,
            default => self::convertValue($value),
        };
    }

    private static function matchesType(string $type, array $needles): bool
    {
        $type = strtolower($type);

        foreach ($needles as $needle) {
            if ($type === $needle || str_starts_with($type, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function toBoolean(string $value): bool
    {
        return !in_array(strtolower(trim($value)), ['false', '0', '', 'no', 'off'], true);
    }
}
