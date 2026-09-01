<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\CriteriaContract;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use CaueSantos\LaravelRequestFilters\Support\RelationIntrospector;
use Illuminate\Database\Eloquent\Builder;

/**
 * Column projection: `select=field,relation.field,relation.nested.field`.
 *
 * A relation field is loaded through a constrained eager-load (`with([$relation
 * => fn ($q) => $q->select(...)])`), so Eloquent can still hydrate it. Every
 * relation whose columns are requested automatically also selects whatever
 * key(s) Eloquent needs to match parents back to children - a belongsTo's
 * foreign key on the local table, or a hasOne/hasMany's foreign key and a
 * belongsToMany's related key on the related table - so a selective `select`
 * never silently breaks relation hydration.
 */
class SelectCriteria extends BaseCriteria implements CriteriaContract
{
    public function apply(): Builder
    {
        $fields = array_values(array_filter(explode(',', (string) $this->request->get('select', ''))));

        $this->checkFields($fields, 'selectable');

        $model = $this->builder->getModel();
        $local = [];
        $byRelation = [];

        foreach ($fields as $field) {
            if (!str_contains($field, '.')) {
                $local[] = ColumnResolver::columnNamePolicy($field);

                continue;
            }

            if (!$this->checkRelationAllowed($field)) {
                continue;
            }

            $relation = ColumnResolver::dotRelations($field, true);
            $column = ColumnResolver::columnNamePolicy(ColumnResolver::getColumnFromDottedRelation($field));
            $byRelation[$relation][] = $column;
        }

        foreach ($byRelation as $relation => $columns) {
            $chain = RelationIntrospector::resolveChain($model, $relation);

            if ($chain === null) {
                continue;
            }

            $lastHop = $chain[array_key_last($chain)];
            $extraKeys = array_unique(array_filter([$lastHop->relatedKey]));

            // Eager-load constraint closures receive the underlying Relation
            // instance (not a plain Builder), but it proxies query methods
            // like select() via __call().
            $this->builder = $this->builder->with([
                $relation => function ($query) use ($columns, $extraKeys) {
                    $query->select(array_values(array_unique([...$extraKeys, ...$columns])));
                },
            ]);

            if (!$lastHop->isReverseForeignKey && !$lastHop->isPivoted()) {
                // belongsTo-style: the FK lives on the *local* side of the last hop.
                $local[] = $lastHop->foreignKey;
            }
        }

        if (!empty($local)) {
            $table = $model->getTable();
            $local[] = $model->getKeyName();
            $selects = array_map(fn ($c) => "{$table}.{$c}", array_unique($local));
            $this->builder = $this->builder->select($selects);
        }

        return $this->builder;
    }
}
