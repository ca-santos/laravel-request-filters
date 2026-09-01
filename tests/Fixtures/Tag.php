<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
