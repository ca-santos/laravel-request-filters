<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A test-only middleware standing in for an application's own auth/rate-limiting middleware. */
class BlockingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return response('blocked by custom middleware', 418);
    }
}
