<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Instance-method variant of {@see RequestFilterTrait}, for classes that are
 * themselves an Eloquent {@see Builder} (e.g. a custom query builder) rather
 * than a {@see \Illuminate\Database\Eloquent\Model}.
 */
trait RequestFilterTrait2
{
    /**
     * @param  string|ModelCriteria|null  $modelCriteria
     *
     * @throws InvalidArgumentException
     */
    public function applyCriteria(string|ModelCriteria|null $modelCriteria = null): Builder
    {
        return ApplyCriteria::applyCriteria($modelCriteria ?? DefaultCriteria::class, $this);
    }
}
