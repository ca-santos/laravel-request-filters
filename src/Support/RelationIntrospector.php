<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Resolves relation metadata (table, keys, pivot, "through" hops) directly
 * from the real Eloquent relation object returned by calling the relation
 * method on the model - never from a consumer-supplied metadata registry
 * (e.g. a `RelationshipsTrait`). This is what lets the engine work with any
 * Eloquent model without the model needing to know this package exists.
 *
 * Polymorphic relations (MorphTo/MorphMany/MorphToMany) are intentionally
 * unsupported for joins/sorting (the related model/table isn't fixed), so
 * {@see self::resolve()} returns null for them; callers treat that the same
 * way they treat "relation doesn't exist" - silently skipping the join.
 */
final class RelationIntrospector
{
    /** @var array<string, RelationInfo|false> */
    private static array $cache = [];

    public static function resolve(Model $model, string $relationName): ?RelationInfo
    {
        $key = $model::class.'::'.$relationName;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] === false ? null : self::$cache[$key];
        }

        $info = self::introspect($model, $relationName);
        self::$cache[$key] = $info ?? false;

        return $info;
    }

    public static function isRelation(Model $model, string $name): bool
    {
        return self::resolve($model, $name) !== null;
    }

    /**
     * Discover every relation a model declares by reflecting over its public,
     * zero-argument methods whose declared return type is an Eloquent
     * `Relation` subtype (`public function company(): BelongsTo`) - the same
     * convention Eloquent itself relies on for relation methods. Never
     * invokes a method that doesn't already declare such a return type, so
     * this is safe to run against any model.
     *
     * @return array<string, RelationInfo>
     */
    public static function discoverAll(Model $model): array
    {
        $discovered = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (!$returnType instanceof \ReflectionNamedType
                || $returnType->isBuiltin()
                || !is_subclass_of($returnType->getName(), Relation::class)) {
                continue;
            }

            if ($info = self::resolve($model, $method->getName())) {
                $discovered[$method->getName()] = $info;
            }
        }

        return $discovered;
    }

    /**
     * Walk a dotted relation path (`a.b.c`) from `$model`, resolving each hop.
     * Returns null as soon as any segment fails to resolve to a real relation.
     *
     * @return list<RelationInfo>|null
     */
    public static function resolveChain(Model $model, string $dottedPath): ?array
    {
        $chain = [];

        foreach (explode('.', $dottedPath) as $segment) {
            $info = self::resolve($model, $segment);

            if (!$info) {
                return null;
            }

            $chain[] = $info;
            $model = new $info->relatedModel;
        }

        return $chain;
    }

    private static function introspect(Model $model, string $relationName): ?RelationInfo
    {
        if (!method_exists($model, $relationName) || !is_callable([$model, $relationName])) {
            return null;
        }

        try {
            $relation = $model->{$relationName}();
        } catch (Throwable) {
            return null;
        }

        if (!$relation instanceof Relation) {
            return null;
        }

        $related = $relation->getRelated();

        return match (true) {
            $relation instanceof BelongsToMany => new RelationInfo(
                type: 'belongsToMany',
                relatedModel: $related::class,
                relatedTable: $related->getTable(),
                relatedKey: $relation->getRelatedKeyName(),
                foreignKey: $relation->getParentKeyName(),
                pivotTable: $relation->getTable(),
                pivotForeignKey: $relation->getForeignPivotKeyName(),
                pivotRelatedKey: $relation->getRelatedPivotKeyName(),
            ),
            $relation instanceof HasOneOrManyThrough => new RelationInfo(
                type: 'hasManyThrough',
                relatedModel: $related::class,
                relatedTable: $related->getTable(),
                relatedKey: $relation->getForeignKeyName(),
                foreignKey: $relation->getLocalKeyName(),
                throughTable: $relation->getParent()->getTable(),
                throughFirstKey: $relation->getFirstKeyName(),
                throughSecondLocalKey: $relation->getSecondLocalKeyName(),
            ),
            $relation instanceof BelongsTo => new RelationInfo(
                type: 'belongsTo',
                relatedModel: $related::class,
                relatedTable: $related->getTable(),
                relatedKey: $relation->getOwnerKeyName(),
                foreignKey: $relation->getForeignKeyName(),
            ),
            $relation instanceof HasOneOrMany => new RelationInfo(
                type: str_contains($relation::class, 'HasMany') ? 'hasMany' : 'hasOne',
                relatedModel: $related::class,
                relatedTable: $related->getTable(),
                relatedKey: $relation->getForeignKeyName(),
                foreignKey: $relation->getLocalKeyName(),
                isReverseForeignKey: true,
            ),
            default => null, // MorphTo / MorphMany / MorphToMany / unsupported custom relations
        };
    }
}
