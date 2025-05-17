<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return $this->fail('Invalid credentials or inactive account', 401);
        }

        // Load role relationship to get the role name
        $employee->load('role');

        // Create token
        $token = $employee->createToken('auth_token')->plainTextToken;

        return $this->ok('Login successful', [
            'token' => $token,
            'full_name' => $employee->first_name . ' ' . $employee->last_name,
            'role' => $employee->role ? $employee->role->name : null,
            'employee_id' => $employee->id,
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
        if (!Hash::check($request->old_password, $employee->password)) {
            return $this->fail('Current password is incorrect', 401);
        }

        // Update password
        $employee->password = $request->new_password;
        $employee->save();

        return $this->ok('Password changed successfully');
    }

    /**
     * Check if token is valid
     */
    public function checkToken(Request $request): JsonResponse
    {
        // If we get here, the token is valid (middleware already checked)
        // Ensure authenticated user exists
        if (!Auth::user()) {
            return $this->ok('Token check completed', [
                'active_token' => false
            ]);
        }

        return $this->ok('Token check completed', [
            'active_token' => true
        ]);
    }

    /**
     * Logout - revoke current token
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that was used to authenticate the current request
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return $this->ok('Logged out successfully');
    }
}
