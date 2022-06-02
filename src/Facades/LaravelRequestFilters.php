<?php

namespace CaueSantos\LaravelRequestFilters\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelRequestFilters extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-query-filters';
    }

}
