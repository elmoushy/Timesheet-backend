<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpenseReviewerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if user has the right role to review expenses
        // Based on the seeder, Admin and Department Manager roles can review expenses
        $allowedRoles = ['Admin', 'Department Manager'];

        // Get user's role through the direct role relationship
        $userRole = $user->role?->name ?? '';

        // Also check user roles through the pivot table
        $userRoles = $user->userRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allUserRoles = array_merge([$userRole], $userRoles);

        $hasReviewerRole = count(array_intersect($allowedRoles, $allUserRoles)) > 0;

        if (!$hasReviewerRole) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to access expense review features'
            ], 403);
        }

        return $next($request);
    }
}
