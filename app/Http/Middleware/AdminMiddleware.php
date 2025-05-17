<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!auth()->check() || !auth()->user()->role || auth()->user()->role->name !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
                'data' => []
            ], 403);
        }

        return $next($request);
    }
}
