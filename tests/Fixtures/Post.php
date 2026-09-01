<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Fixtures;

use CaueSantos\LaravelRequestFilters\Criteria\CriteriaBuilder;
use CaueSantos\LaravelRequestFilters\Criteria\FilterableByDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use FilterableByDates;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function criteria(): CriteriaBuilder
    {
        return CriteriaBuilder::make();
    }
}
