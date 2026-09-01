<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Fixtures;

use CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder;
use CaueSantos\LaravelRequestFilters\Criteria\ModelCriteriaContract;
use CaueSantos\LaravelRequestFilters\Criteria\RequestFilterTrait;
use CaueSantos\LaravelRequestFilters\Support\ColumnResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    use RequestFilterTrait;

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public static function criteria(): string|ModelCriteriaContract
    {
        return CriteriaBuilder::make()
            ->setSearchable(['first_name', 'last_name', 'email', 'company.name'])
            ->computed('full_name', fn ($query) => ColumnResolver::concat($query, ['first_name', 'last_name']))
            ->counter('posts_count', 'posts')
            ->counter('published_posts_count', 'posts', fn ($q) => $q->whereNotNull('published_at'))
            ->filterUsing('is_adult', function ($query, string $operator, mixed $value) {
                $value ? $query->where('age', '>=', 18) : $query->where('age', '<', 18);
            })
            ->sortUsing('full_name_reversed', function ($query, string $direction) {
                $query->orderByRaw("last_name {$direction}, first_name {$direction}");
            })
            ->alias('email_address', 'email');
    }
}
