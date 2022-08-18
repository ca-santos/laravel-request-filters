<?php

namespace CaueSantos\LaravelRequestFilters;

class Helpers
{

    public static function convertValue($value)
    {

        if (is_null($value)) {
            return null;
        }

        if ($value === 'false' || $value === 'true') {
            return $value === 'true';
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return $value;

    }

}
