<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PageRolePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Helper method for successful responses.
     */
    private function ok(string $msg, $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => $data], $code);
    }

    /**
     * Helper method for error responses.
     */
    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => []], $code);
    }

    /**
     * Login and generate token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Find the employee by email
        $employee = Employee::where('work_email', $request->email)
            ->where('user_status', 'active')
            ->first();

        if (! $employee || ! Hash::check($request->password, $employee->password)) {
            return $this->fail('Invalid credentials or inactive account', 401);
        }

        // Load active user roles with their roles
        $employee->load(['activeUserRoles.role']);

        // Get allowed pages for all user's active roles
        $allowedPages = collect();

        if ($employee->activeUserRoles->isNotEmpty()) {
            // Get all active role IDs from user roles
            $roleIds = $employee->activeUserRoles->pluck('role_id')->toArray();

            // Get all allowed pages for these roles (avoiding duplicates)
            $allowedPages = PageRolePermission::with('page')
                ->whereIn('role_id', $roleIds)
                ->where('is_active', true)
                ->whereHas('page', function ($query) {
                    $query->where('is_active', true);
                })
                ->get()
                ->pluck('page')
                ->unique('id') // Remove duplicate pages by ID
                ->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'name' => $page->name,
                    ];
                })
                ->values(); // Reset array keys after unique
        }

        // Get the primary role name (first active role)
        $primaryRole = $employee->activeUserRoles->first()?->role;

        // Create token
        $token = $employee->createToken('auth_token')->plainTextToken;

        return $this->ok('Login successful', [
            'token' => $token,
            'full_name' => $employee->first_name.' '.$employee->last_name,
            'role' => $primaryRole ? $primaryRole->name : null,
            'employee_id' => $employee->id,
            'allowed_pages' => $allowedPages,
        ]);
    }

    /**
     * Change employee password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:xxx_employees,work_email',
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|different:old_password',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Find employee
        $employee = Employee::where('work_email', $request->email)->first();

        // Check old password
        if (! Hash::check($request->old_password, $employee->password)) {
            return $this->fail('Current password is incorrect', 401);
        }

        // Update password
        $employee->password = $request->new_password;
        $employee->save();

        return $this->ok('Password changed successfully');
    }

    /**
     * Check if JWT token is valid
     */
    public function checkToken(Request $request): JsonResponse
    {
        // If we get here, the JWT token is valid (middleware already checked)
        // Get authenticated user from JWT middleware
        $user = $request->attributes->get('auth_user');

        if (! $user) {
            return $this->ok('Token check completed', [
                'active_token' => false,
            ]);
        }

        return $this->ok('Token check completed', [
            'active_token' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->work_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'employee_code' => $user->employee_code,
                'is_active' => $user->isActive(),
            ],
        ]);
    }

    /**
     * Logout - JWT tokens are stateless, so we just return success
     * For actual token revocation, client should discard the token
     */
    public function logout(Request $request): JsonResponse
    {
        // JWT tokens are stateless, so we can't revoke them server-side
        // The client should discard the token
        // For refresh token revocation, use the SSO logout endpoint instead

        $user = $request->attributes->get('auth_user');
        if ($user) {
            Log::info('JWT logout', [
                'user_id' => $user->id,
                'email' => $user->work_email,
            ]);
        }

        return $this->ok('Logged out successfully. Please discard the token on client side.');
    }
}
