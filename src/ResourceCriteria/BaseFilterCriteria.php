<?php

namespace CaueSantos\LaravelRequestFilters\ResourceCriteria;

use App\Core\Resources\BaseResource;
use CaueSantos\Brik\Fields\Field;
use CaueSantos\Brik\Filters\DateFilterType;
use CaueSantos\Brik\Filters\FilterableFieldContract;
use CaueSantos\Brik\Filters\FilterType;
use CaueSantos\Brik\Http\Requests\BrikFormRequest;
use CaueSantos\Support\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class BaseFilterCriteria extends BaseCriteria implements CriteriaContract
{
    /**
     * Character used with the SQL ESCAPE clause so LIKE wildcards in user input
     * are matched literally. Passed as a binding to avoid engine-specific
     * differences in string-literal backslash handling (MariaDB vs SQLite).
     */
    private const LIKE_ESCAPE_CHAR = '\\';

    /**
     * SQL fragment appended to LIKE clauses. The escape character itself is
     * bound as a parameter so the clause is safe under MariaDB (where a raw
     * `'\\'` literal depends on NO_BACKSLASH_ESCAPES) and SQLite (where
     * `'\\'` is parsed as two characters and rejected).
     */
    private const LIKE_ESCAPE_SQL = ' ESCAPE ?';

    protected array $filterOperators = [];

    /** @var Collection<FilterableFieldContract> */
    protected Collection $resourceFilters;

    public function __construct(Builder $builder, BaseResource $resource, BrikFormRequest $request)
    {
        parent::__construct($builder, $resource, $request);

        $this->filterOperators = [...FilterType::all(), ...DateFilterType::all()];
        $this->setResourceFilters();
    }

    private function setResourceFilters(): void
    {
        $this->resourceFilters = $this->resource
            ->indexFieldsNotResolving(new BrikFormRequest)
            ->filter(function (Field $field) {
                return !empty(class_implements($field)[FilterableFieldContract::class]);
            })
            ->map(fn (FilterableFieldContract $field) => $field->makeFilter())
            ->values();
    }

    protected function dataConverters(array $columns): array
    {
        $columns = collect($columns)
            ->map(fn ($col) => $this::escapeColumn($col))
            ->toArray();

        return [
            'concat' => 'CONCAT('.implode('," ",', $columns).')',
        ];
    }

    protected function operatorQuery(Builder|QueryBuilder $query, array $columns, string $operator, ?string $converter, array $value, bool $intoSubQuery = false, bool $useHaving = true): Builder|QueryBuilder
    {

        $isNegative = Str::contains($operator, '!');
        $originalOperator = $operator;
        $operator = str_replace('!', '', $operator);

        $properConverter = $this->dataConverters($columns)[$converter] ?? null;
        $columns = $properConverter ? [$properConverter] : $columns;

        $whereFn = $useHaving ? 'orHavingRaw' : 'orWhereRaw';

        $escapedColumns = collect($columns)
            ->map(fn ($col) => 'COALESCE('.$this::escapeColumn($col).", '')");
        $concat = 'CONCAT('.($escapedColumns->implode(", ' - ', ")).')';

        $complexOperators = [

            'empty' => function ($subQuery) use ($query, $columns, $isNegative, $whereFn, $intoSubQuery) {

                if ($intoSubQuery) {
                    return $query;
                }

                $fn = $isNegative ? 'IS NOT NULL' : 'IS NULL';

                foreach ($columns as $col) {
                    $second = $isNegative ? " AND {$this::escapeColumn($col)} != ''" : " OR {$this::escapeColumn($col)} = ''";
                    $query = $query->{$whereFn}("{$this::escapeColumn($col)} {$fn} {$second}");
                }

                return $query;

            },

            'in' => function ($query) use ($columns, $value, $isNegative, $whereFn) {
                $fn = $isNegative ? 'NOT IN' : 'IN';
                $bindings = array_values($value);
                $placeholders = implode(',', array_fill(0, count($bindings), '?'));
                foreach ($columns as $col) {
                    $query = $query->{$whereFn}("{$this::escapeColumn($col)} {$fn} ({$placeholders})", $bindings);
                }

                return $query;
            },

            'between' => function ($query) use ($columns, $value, $isNegative, $whereFn) {
                $fn = $isNegative ? 'NOT BETWEEN' : 'BETWEEN';
                $bindings = [$value[0] ?? null, $value[1] ?? null];
                foreach ($columns as $col) {
                    $query = $query->{$whereFn}("{$this::escapeColumn($col)} {$fn} ? AND ?", $bindings);
                }

                return $query;
            },

            'like' => function ($query) use ($value, $isNegative, $whereFn, $concat) {
                $fn = $isNegative ? 'NOT LIKE' : 'LIKE';
                $pattern = '%'.self::escapeLikePattern($value[0]).'%';

                return $query->{$whereFn}("{$concat} {$fn} ?".self::LIKE_ESCAPE_SQL, [$pattern, self::LIKE_ESCAPE_CHAR]);
            },

            'starts' => function ($query) use ($value, $isNegative, $whereFn, $concat) {
                $fn = $isNegative ? 'NOT LIKE' : 'LIKE';
                $pattern = self::escapeLikePattern($value[0]).'%';

                return $query->{$whereFn}("{$concat} {$fn} ?".self::LIKE_ESCAPE_SQL, [$pattern, self::LIKE_ESCAPE_CHAR]);
            },

            'ends' => function ($query) use ($value, $isNegative, $whereFn, $concat) {
                $fn = $isNegative ? 'NOT LIKE' : 'LIKE';
                $pattern = '%'.self::escapeLikePattern($value[0]);

                return $query->{$whereFn}("{$concat} {$fn} ?".self::LIKE_ESCAPE_SQL, [$pattern, self::LIKE_ESCAPE_CHAR]);
            },

            'default' => function ($query) use ($columns, $originalOperator, $value, $whereFn) {

                $colsExpression = [];

                foreach ($columns as $col) {

                    if (Helpers::isValidDate($value[0], 'Y-m-d')) {
                        $colsExpression[] = "DATE({$this::escapeColumn($col)})";
                    } elseif (Helpers::isValidDate($value[0])) {
                        $colsExpression[] = "TIMESTAMP({$this::escapeColumn($col)})";
                    } else {
                        $colsExpression[] = $this::escapeColumn($col);
                    }

                }

                $finalValue = is_numeric($value[0]) ? ($value[0] + 0) : $value[0];

                if (count($colsExpression) === 1) {
                    return $query->{$whereFn}(
                        "{$colsExpression[0]} {$originalOperator} ?",
                        [$finalValue]
                    );
                }

                return $query->{$whereFn}(
                    'CONCAT('.(collect($colsExpression)->implode(", ' - ', ")).')'." {$originalOperator} ?",
                    [$finalValue]
                );

            },

        ];

        if (in_array($operator, array_keys($complexOperators))) {

            return $query->{$useHaving ? 'having' : 'where'}(fn ($q) => $complexOperators[$operator]($q));

        } elseif (Str::startsWith($operator, 'whereDateIs')) {

            return $query->{$useHaving ? 'having' : 'where'}(
                function ($query) use ($columns, $operator, $value) {
                    foreach ($columns as $col) {
                        $query = $query->{$operator}($col, filled($value[0]) ? $value[0] : 0);
                    }

                    return $query;
                }
            );

        }

        return $query->{$useHaving ? 'having' : 'where'}(fn ($q) => $complexOperators['default']($q));

    }

    /**
     * Escape LIKE wildcard characters so user input is matched literally.
     *
     * Backslash must be escaped first so the replacement escapes for `%` and `_`
     * aren't re-escaped by the next pass.
     */
    private static function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
