<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! auth()->check() || ! auth()->user()->role || auth()->user()->role->name !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
                'data' => [],
            ], 403);
        }

        return $next($request);
    }
}
