<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\Resources\BaseResource;
use CaueSantos\Brik\Http\Requests\BrikFormRequest;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * @deprecated Legacy engine kept only for backward compatibility with code in
 *             `packages/brik` and `app/` that constructs these classes
 *             directly with a Brik `BaseResource`/`BrikFormRequest`. New code
 *             should use `CaueSantos\LaravelRequestFilters\Criteria\*`
 *             instead, which is Eloquent-only and doesn't require Brik. See
 *             this package's `SYSTEM_SPEC.md` / final report for context.
 *
 * @mixin Builder
 */
class BaseCriteria extends Builder
{
    protected Builder|QueryBuilder $builder;

    protected BrikFormRequest $request;

    protected BaseResource $resource;

    public function __construct(Builder $builder, BaseResource $resource, BrikFormRequest $request)
    {
        parent::__construct($builder->getQuery());

        $this->builder = $builder;
        $this->model = $builder->getModel();
        $this->resource = $resource;
        $this->request = $request;
    }

    /**
     * @throws Exception
     */
    protected function checkFields(array $filters, string $type): bool
    {

        $filterType = $this->criteriaConfig->{$type}();

        if (isset($filterType[0]) && $filterType[0] === '*') {
            return true;
        }

        if (array_diff($filters, $filterType)) {
            throw new Exception('Not allowed filters: '.implode(', ', array_diff($filters, $filterType)));
        }

        return true;
    }

    protected function clearFields(array $filters, string $type): array
    {

        $filtersDefined = $this->criteriaConfig->{$type}();

        if (isset($filtersDefined[0]) && $filtersDefined[0] === '*') {
            return $filters;
        }

        $allowed = [];
        foreach ($filters as $key => $filter) {
            if (!in_array('!'.$key, $filtersDefined) && in_array($key, $filtersDefined)) {
                $allowed[$key] = $filter;
            }
        }

        return $allowed;

    }

    public static function columnNamePolicy(string $column): string
    {
        return Str::snake($column);
    }

    public static function escapeColumn(string|array $column): string
    {
        $rawColum = is_string($column) ? $column : implode('.', $column);

        $column = is_string($column) ? explode('.', $column) : $column;
        $lastPart = end($column);
        array_pop($column);

        $trimmed = trim($rawColum);

        if (
            Str::contains(Str::replace(' ', '', Str::lower($rawColum)), 'concat(') ||
            (Str::startsWith($trimmed, '(') && Str::endsWith($trimmed, ')'))
        ) {
            return $rawColum;
        }

        $column[] = static::columnNamePolicy($lastPart);

        return collect($column)->map(fn ($col) => '`'.str_replace('`', '``', $col).'`')->implode('.');
    }

    public static function dotRelations(string $relation, bool $endingWithColumn = false): string
    {
        $relations = explode('.', $relation);
        if ($endingWithColumn) {
            array_pop($relations);
        }

        return implode('.', $relations);
    }

    public static function getColumnFromDottedRelation(string $relation): string
    {
        $relations = explode('.', $relation);

        return end($relations);
    }

    protected function resolveFields(array $columns, ?string $table = null): array
    {
        $table = "`{$table}`." ?? '';

        return collect($columns)
            ->map(function ($column) use ($table) {

                if (Str::contains($column, '->')) {

                    $ex = explode('->', $column);
                    $col = str_replace('`', '``', $ex[0]);
                    $nested = str_replace("'", "''", collect($ex)->forget(0)->implode('.'));

                    return "JSON_VALUE({$table}`{$col}`, '$.".$nested."')";

                }

                return $column;

            })
            ->toArray();
    }
}
