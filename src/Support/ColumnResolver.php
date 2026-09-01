<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Column-name policy and safe-escaping helpers shared by every stage of the
 * engine. Kept dependency-free (no Eloquent model needed) so it can be unit
 * tested in isolation and reused by both filtering and sorting.
 */
final class ColumnResolver
{
    /**
     * The naming convention applied to every column coming from a request:
     * request fields are snake_cased before being matched against the
     * database (`fullName` -> `full_name`).
     */
    public static function columnNamePolicy(string $column): string
    {
        return Str::snake($column);
    }

    /**
     * Safely quote a column (or dotted `table.column` path) for use inside a
     * raw SQL fragment. Recognises two cases that must NOT be treated as plain
     * columns: SQL function calls (e.g. `CONCAT(...)`) and already-parenthesised
     * expressions - both are returned untouched since they are developer-authored
     * expressions, not user input.
     */
    public static function escapeColumn(string|array $column): string
    {
        $raw = is_string($column) ? $column : implode('.', $column);
        $trimmed = trim($raw);

        if (
            Str::contains(Str::replace(' ', '', Str::lower($raw)), 'concat(')
            || (Str::startsWith($trimmed, '(') && Str::endsWith($trimmed, ')'))
        ) {
            return $raw;
        }

        $parts = is_string($column) ? explode('.', $column) : $column;
        $last = array_pop($parts);
        $parts[] = static::columnNamePolicy($last);

        return collect($parts)
            ->map(fn ($part) => '`'.str_replace('`', '``', $part).'`')
            ->implode('.');
    }

    /**
     * Validate a column/relation-path name coming straight from user input
     * before it is ever concatenated into raw SQL. Only allows the character
     * set a legitimate column or dotted relation path can contain, plus a
     * `->segment` suffix for JSON columns. Rejects everything else (quotes,
     * whitespace, SQL keywords, comment sequences, etc).
     */
    public static function isSafeColumnName(string $column): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_.:-]+(?:->[a-zA-Z0-9_.]+)*$/', $column);
    }

    /**
     * Turn a JSON-path column (`meta->address->city`) into a `JSON_VALUE`
     * expression. Returns the column untouched when it isn't a JSON path.
     */
    public static function resolveJsonPath(string $column, ?string $table = null): string
    {
        if (!Str::contains($column, '->')) {
            return $column;
        }

        $segments = explode('->', $column);
        $base = str_replace('`', '``', array_shift($segments));
        $path = str_replace("'", "''", implode('.', $segments));
        $prefix = $table ? '`'.str_replace('`', '``', $table).'`.' : '';

        return "JSON_VALUE({$prefix}`{$base}`, '$.{$path}')";
    }

    /**
     * Everything before the last dotted segment of a relation path, e.g.
     * `a.b.c` -> `a.b`. When `$endingWithColumn` is false the whole string
     * (including the trailing column) is returned untouched.
     */
    public static function dotRelations(string $relation, bool $endingWithColumn = false): string
    {
        $relations = explode('.', $relation);
        if ($endingWithColumn) {
            array_pop($relations);
        }

        return implode('.', $relations);
    }

    /** The last dotted segment of a relation path, e.g. `a.b.c` -> `c`. */
    public static function getColumnFromDottedRelation(string $relation): string
    {
        $relations = explode('.', $relation);

        return end($relations);
    }

    /**
     * A driver-aware column concatenation expression: `CONCAT(a, ' ', b)` on
     * MySQL/MariaDB, `a || ' ' || b` on SQLite/PostgreSQL (neither of which
     * has a `CONCAT()` builtin). Useful for building a portable
     * {@see \CaueSantos\LaravelRequestFilters\Support\ComputedField} expression.
     */
    public static function concat(Builder $query, array $columns, string $separator = ' '): string
    {
        $escaped = array_map(fn ($c) => self::escapeColumn($c), $columns);
        $quotedSeparator = "'".str_replace("'", "''", $separator)."'";

        if (in_array($query->getConnection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            return implode(" || {$quotedSeparator} || ", $escaped);
        }

        return 'CONCAT('.collect($escaped)->implode(", {$quotedSeparator}, ").')';
    }
}
