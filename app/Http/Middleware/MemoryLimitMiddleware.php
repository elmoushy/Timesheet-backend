<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MemoryLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $limit
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $limit = '-1')
    {
        // Store original memory limit
        $originalLimit = ini_get('memory_limit');

        // Set new memory limit
        ini_set('memory_limit', $limit);

        // Enable garbage collection
        gc_enable();

        $response = $next($request);

        // Restore original memory limit
        ini_set('memory_limit', $originalLimit);

        // Force garbage collection
        gc_collect_cycles();

        return $response;
    }
}
