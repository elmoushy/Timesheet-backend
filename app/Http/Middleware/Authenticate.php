<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't attempt to redirect
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        // For web requests, redirect to login page if the route exists
        return $request->expectsJson() ? null : (route_has('login') ? route('login') : '/login');
    }
}
