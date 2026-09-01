<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Http\Middleware;

use CaueSantos\LaravelRequestFilters\RequestFiltersServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the `/filters/metadata` routes - they describe a model's real
 * columns, relations, and attributes, which is not something every
 * application wants exposed to any caller by default.
 *
 * @see RequestFiltersServiceProvider::auth() to override who is authorized.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!RequestFiltersServiceProvider::check($request)) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
