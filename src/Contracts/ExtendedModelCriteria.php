<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Contracts;

use CaueSantos\LaravelRequestFilters\Support\ComputedField;
use CaueSantos\LaravelRequestFilters\Support\RelationCounter;
use Closure;

/**
 * Optional, additive capabilities a criteria class may declare on top of the
 * base {@see ModelCriteria} whitelist. The engine checks
 * `instanceof ExtendedModelCriteria` before calling any of these - a criteria
 * class that only implements {@see ModelCriteria} keeps working exactly as
 * before, with none of these capabilities.
 *
 * This is what replaces Brik-specific concepts (`ConcatenationField`,
 * `FormulaField`, counter fields, `Filter::filterUsingCallback()`) with a
 * generic, Eloquent-only equivalent.
 */
interface ExtendedModelCriteria extends ModelCriteria
{
    /** @return array<string, ComputedField> keyed by field name */
    public function computedFields(): array;

    /** @return array<string, RelationCounter> keyed by field name */
    public function counters(): array;

    /**
     * @return array<string, Closure(\Illuminate\Database\Eloquent\Builder, string $operator, mixed $value, string $boolean): void>
     *              keyed by field name - takes over filtering for that field entirely
     */
    public function customFilters(): array;

    /**
     * @return array<string, Closure(\Illuminate\Database\Eloquent\Builder, string $direction): void>
     *              keyed by field name - takes over ordering for that field entirely
     */
    public function customSorts(): array;

    /** @return array<string, string> alias => real dotted field/relation path */
    public function aliases(): array;
}
