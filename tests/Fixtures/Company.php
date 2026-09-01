<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Fixtures;

use CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class);
    }

    public function criteria(): CriteriaBuilder
    {
        return CriteriaBuilder::make();
    }
}
