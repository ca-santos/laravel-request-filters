<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Unit;

use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use CaueSantos\LaravelRequestFilters\Support\DateShortcuts;
use CaueSantos\LaravelRequestFilters\Support\Values;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_convert_value_casts_booleans_and_numbers(): void
    {
        $this->assertTrue(Values::convertValue('true'));
        $this->assertFalse(Values::convertValue('false'));
        $this->assertSame(5, Values::convertValue('5'));
        $this->assertSame(5.5, Values::convertValue('5.5'));
        $this->assertSame('0123', Values::convertValue('0123')); // leading zero preserved
        $this->assertSame('abc', Values::convertValue('abc'));
        $this->assertNull(Values::convertValue(null));
    }

    public function test_cast_for_column_type_uses_the_real_column_type_instead_of_guessing(): void
    {
        // The whole point: a numeric-*looking* value in a text column (a
        // status code, a zero-padded reference) is no longer coerced to a
        // number just because it looks like one.
        $this->assertSame('42', Values::castForColumnType('42', 'varchar'));
        $this->assertSame('0123', Values::castForColumnType('0123', 'varchar'));
        $this->assertSame(['1', '2'], Values::castForColumnType(['1', '2'], 'text'));

        $this->assertSame(42, Values::castForColumnType('42', 'integer'));
        $this->assertSame(42, Values::castForColumnType('0042', 'bigint')); // a real int column: leading zeros don't matter
        $this->assertSame([1, 2], Values::castForColumnType(['1', '2'], 'int'));

        $this->assertSame(4.5, Values::castForColumnType('4.5', 'decimal'));
        $this->assertSame(4.5, Values::castForColumnType('4.5', 'numeric'));

        $this->assertTrue(Values::castForColumnType('true', 'boolean'));
        $this->assertFalse(Values::castForColumnType('false', 'boolean'));
        $this->assertFalse(Values::castForColumnType('0', 'bool'));
    }

    public function test_cast_for_column_type_falls_back_to_the_generic_heuristic(): void
    {
        // Unknown/unmapped type (dates are intentionally left to the
        // dedicated date-handling code elsewhere).
        $this->assertSame('2024-01-01', Values::castForColumnType('2024-01-01', 'date'));
        // No type available at all (computed field, unresolvable column, ...).
        $this->assertSame(5, Values::castForColumnType('5', null));
        $this->assertSame('0123', Values::castForColumnType('0123', null));
        // A value that doesn't fit the bucket's shape falls back rather than
        // being left as a broken raw string ("true" bound against an int column).
        $this->assertTrue(Values::castForColumnType('true', 'integer'));
    }

    public function test_sanitize_value_strips_control_characters(): void
    {
        $this->assertSame("hello", Values::sanitizeValue("hello\x00\x01"));
    }

    public function test_escape_like_pattern_escapes_wildcards(): void
    {
        $this->assertSame('100\\%', Values::escapeLikePattern('100%'));
        $this->assertSame('a\\_b', Values::escapeLikePattern('a_b'));
        $this->assertSame('a\\\\b', Values::escapeLikePattern('a\\b'));
    }

    public function test_column_name_policy_snake_cases(): void
    {
        $this->assertSame('full_name', ColumnResolver::columnNamePolicy('fullName'));
    }

    public function test_escape_column_leaves_concat_expressions_untouched(): void
    {
        $expr = "CONCAT(first_name, ' ', last_name)";
        $this->assertSame($expr, ColumnResolver::escapeColumn($expr));
    }

    public function test_is_safe_column_name_rejects_sql_metacharacters(): void
    {
        $this->assertTrue(ColumnResolver::isSafeColumnName('users.first_name'));
        $this->assertTrue(ColumnResolver::isSafeColumnName('meta->address->city'));
        $this->assertFalse(ColumnResolver::isSafeColumnName('id; DROP TABLE users;--'));
        $this->assertFalse(ColumnResolver::isSafeColumnName("id' OR '1'='1"));
    }

    public function test_date_shortcuts_half_open_range(): void
    {
        $range = DateShortcuts::range('this_week');

        $this->assertTrue($range->from->lessThan($range->to));
        $this->assertFalse($range->contains($range->to->copy()));
    }

    public function test_date_shortcuts_requires_n(): void
    {
        $this->assertTrue(DateShortcuts::requiresN('last_n_weeks'));
        $this->assertTrue(DateShortcuts::requiresN('n_days_ago'));
        $this->assertFalse(DateShortcuts::requiresN('today'));
        $this->assertFalse(DateShortcuts::requiresN('this_financial_year'));
    }
}
