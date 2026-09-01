<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Thin, cached wrapper around Laravel's native schema introspection
 * (`Schema::getColumnListing()`/`Schema::getColumns()`) - no `doctrine/dbal`
 * required. Used to tell a real database column apart from a relation name or
 * a computed field when resolving a sort/select target.
 */
final class SchemaIntrospector
{
    /** @var array<string, list<string>> */
    private static array $columnNameCache = [];

    /** @return list<string> column names, or an empty array when they can't be determined */
    public static function columnNames(Model $model): array
    {
        $key = ($model->getConnectionName() ?? 'default').'.'.$model->getTable();

        if (isset(self::$columnNameCache[$key])) {
            return self::$columnNameCache[$key];
        }

        try {
            $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
        } catch (Throwable) {
            $columns = [];
        }

        return self::$columnNameCache[$key] = $columns;
    }

    /**
     * Column metadata (name, type, nullable, default) for `$table`, without a
     * hard dependency on `doctrine/dbal`: prefers Laravel's native
     * `Schema::getColumns()` (available since Laravel 11) and falls back to
     * `doctrine/dbal` when present on an older Laravel version, else to bare
     * column names only.
     *
     * @return list<array{name: string, type: ?string, nullable: ?bool, default: mixed}>
     */
    public static function columns(string $table, ?string $connection = null): array
    {
        $schema = Schema::connection($connection);

        if (method_exists($schema, 'getColumns')) {
            try {
                return array_map(static fn (array $c) => [
                    'name' => $c['name'],
                    'type' => $c['type_name'] ?? $c['type'] ?? null,
                    'nullable' => $c['nullable'] ?? null,
                    'default' => $c['default'] ?? null,
                ], $schema->getColumns($table));
            } catch (Throwable) {
                // fall through to the legacy/bare-name paths below
            }
        }

        if (class_exists(\Doctrine\DBAL\Types\Type::class) && method_exists($schema, 'getConnection')) {
            try {
                $manager = $schema->getConnection()->getDoctrineSchemaManager();

                return array_map(static fn ($c) => [
                    'name' => $c->getName(),
                    'type' => $c->getType()->getName(),
                    'nullable' => !$c->getNotnull(),
                    'default' => $c->getDefault(),
                ], $manager->listTableColumns($table));
            } catch (Throwable) {
                // fall through
            }
        }

        try {
            return array_map(static fn (string $name) => [
                'name' => $name, 'type' => null, 'nullable' => null, 'default' => null,
            ], $schema->getColumnListing($table));
        } catch (Throwable) {
            return [];
        }
    }
}
