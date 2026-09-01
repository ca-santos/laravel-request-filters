<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ExtendedModelCriteria;
use CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @mixin Builder
 */
abstract class BaseCriteria extends Builder
{
    protected Builder $builder;

    protected Request|Collection $request;

    protected ModelCriteria $criteriaConfig;

    /**
     * @param  string|ModelCriteria  $modelCriteria  a criteria class-string
     *                                                 (instantiated fresh) or an
     *                                                 already-configured instance
     *                                                 (e.g. a {@see CriteriaBuilder}),
     *                                                 used as-is so its configuration
     *                                                 is preserved.
     */
    public function __construct(Builder $model, Request|Collection $request, string|ModelCriteria $modelCriteria)
    {
        parent::__construct($model->getQuery());

        $this->builder = $model;
        $this->request = $request;
        $this->criteriaConfig = is_string($modelCriteria) ? new $modelCriteria : $modelCriteria;
    }

    protected function extendedCriteria(): ?ExtendedModelCriteria
    {
        return $this->criteriaConfig instanceof ExtendedModelCriteria ? $this->criteriaConfig : null;
    }

    /** @throws InvalidArgumentException when any of `$fields` isn't allowed for `$type` */
    protected function checkFields(array $fields, string $type): bool
    {
        $whitelist = $this->criteriaConfig->{$type}();

        $disallowed = array_values(array_filter(
            $fields,
            fn ($field) => !self::isFieldAllowed((string) $field, $whitelist)
        ));

        if (!empty($disallowed)) {
            throw new InvalidArgumentException('Not allowed filters: '.implode(', ', $disallowed));
        }

        return true;
    }

    /** Silently drop any field not allowed for `$type`, keeping the rest. */
    protected function clearFields(array $fields, string $type): array
    {
        $whitelist = $this->criteriaConfig->{$type}();

        $allowed = [];
        foreach ($fields as $key => $value) {
            if (self::isFieldAllowed((string) $key, $whitelist)) {
                $allowed[$key] = $value;
            }
        }

        return $allowed;
    }

    /**
     * `$whitelist === ['*']` allows every field except ones explicitly excluded
     * via a `!field` entry; otherwise a field must be explicitly listed (and
     * not excluded) to be allowed.
     */
    public static function isFieldAllowed(string $field, array $whitelist): bool
    {
        if (in_array('!'.$field, $whitelist, true)) {
            return false;
        }

        if (isset($whitelist[0]) && $whitelist[0] === '*') {
            return true;
        }

        return in_array($field, $whitelist, true);
    }

    /**
     * Whether the relation portion of a dotted field path (`company.name` ->
     * `company`) is allowed by the criteria's `relatable()` whitelist. Fields
     * with no dot (no relation involved) are always allowed by definition.
     */
    protected function checkRelationAllowed(string $field): bool
    {
        if (!str_contains($field, '.')) {
            return true;
        }

        $relation = ColumnResolver::dotRelations($field, true);

        return self::isFieldAllowed($relation, $this->criteriaConfig->relatable());
    }

    /** Resolve a request field name through the criteria's declared aliases, if any. */
    protected function resolveAlias(string $field): string
    {
        $aliases = $this->extendedCriteria()?->aliases() ?? [];

        return $aliases[$field] ?? $field;
    }

    public static function columnNamePolicy(string $column): string
    {
        return ColumnResolver::columnNamePolicy($column);
    }

    public static function escapeColumn(string|array $column): string
    {
        return ColumnResolver::escapeColumn($column);
    }

    public static function dotRelations(string $relation, bool $endingWithColumn = false): string
    {
        return ColumnResolver::dotRelations($relation, $endingWithColumn);
    }

    public static function getColumnFromDottedRelation(string $relation): string
    {
        return ColumnResolver::getColumnFromDottedRelation($relation);
    }
}
