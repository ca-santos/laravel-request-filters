<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Criteria;

use CaueSantos\LaravelRequestFilters\Contracts\ModelCriteria;

/**
 * @deprecated kept for backward compatibility - implement {@see ModelCriteria}
 *             (optionally with {@see \CaueSantos\LaravelRequestFilters\Contracts\ExtendedModelCriteria})
 *             directly instead. This interface adds nothing on top of it; it
 *             exists only so existing `implements ModelCriteriaContract`
 *             classes keep working unmodified.
 */
interface ModelCriteriaContract extends ModelCriteria
{
}
