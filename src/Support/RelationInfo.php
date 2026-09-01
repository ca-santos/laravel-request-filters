<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

/**
 * Metadata describing one leg of a dotted relation path, resolved directly
 * from a real Eloquent relation instance (never from a consumer-provided
 * "relation registry"). Used by the sort/select engine to build joins.
 */
final class RelationInfo
{
    public function __construct(
        public readonly string $type,
        public readonly string $relatedModel,
        public readonly string $relatedTable,
        public readonly string $relatedKey,
        public readonly string $foreignKey,
        public readonly bool $isReverseForeignKey = false,
        public readonly ?string $pivotTable = null,
        public readonly ?string $pivotForeignKey = null,
        public readonly ?string $pivotRelatedKey = null,
        public readonly ?string $throughTable = null,
        public readonly ?string $throughFirstKey = null,
        public readonly ?string $throughSecondLocalKey = null,
    ) {
    }

    public function isPivoted(): bool
    {
        return $this->pivotTable !== null;
    }

    public function isThrough(): bool
    {
        return $this->throughTable !== null;
    }
}
