<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Models\Role;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    /**
     * Display a listing of user roles.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = UserRole::with(['user', 'role', 'assignedBy']);

            // Filter by active status if specified
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by user if specified
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by role if specified
            if ($request->has('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            // Search by user name if specified
            if ($request->has('search')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('first_name', 'like', '%' . $request->search . '%')
                      ->orWhere('last_name', 'like', '%' . $request->search . '%')
                      ->orWhere('work_email', 'like', '%' . $request->search . '%');
                });
            }

            $userRoles = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $userRoles,
                'message' => 'User roles retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user role assignment.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'role_id' => 'required|exists:xxx_roles,id',
                'user_id' => 'required|exists:xxx_employees,id',
                'is_active' => 'boolean',
                'assigned_by' => 'nullable|exists:xxx_employees,id'
            ]);

            // Check if the combination already exists
            $existingUserRole = UserRole::where('role_id', $validatedData['role_id'])
                                      ->where('user_id', $validatedData['user_id'])
                                      ->first();

            if ($existingUserRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role assignment already exists'
                ], 422);
            }

            // Set assigned_by to current authenticated user if not provided
            if (!isset($validatedData['assigned_by']) && Auth::check()) {
                $validatedData['assigned_by'] = Auth::id();
            }

            $userRole = UserRole::create($validatedData);
            $userRole->load(['user', 'role', 'assignedBy']);

            return response()->json([
                'success' => true,
                'data' => $userRole,
                'message' => 'User role assigned successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user role.
     */
    public function show($id): JsonResponse
    {
        try {
            $userRole = UserRole::with(['user', 'role', 'assignedBy'])->find($id);

            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $userRole,
                'message' => 'User role retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user role.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $userRole = UserRole::find($id);

            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role not found'
                ], 404);
            }

            $validatedData = $request->validate([
                'role_id' => 'sometimes|required|exists:xxx_roles,id',
                'user_id' => 'sometimes|required|exists:xxx_employees,id',
                'is_active' => 'sometimes|boolean',
                'assigned_by' => 'sometimes|nullable|exists:xxx_employees,id'
            ]);

            // Check if the new combination already exists (excluding current record)
            if (isset($validatedData['role_id']) || isset($validatedData['user_id'])) {
                $roleId = $validatedData['role_id'] ?? $userRole->role_id;
                $userId = $validatedData['user_id'] ?? $userRole->user_id;

                $existingUserRole = UserRole::where('role_id', $roleId)
                                          ->where('user_id', $userId)
                                          ->where('user_roles_id', '!=', $id)
                                          ->first();

                if ($existingUserRole) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User role assignment already exists'
                    ], 422);
                }
            }

            $userRole->update($validatedData);
            $userRole->load(['user', 'role', 'assignedBy']);

            return response()->json([
                'success' => true,
                'data' => $userRole,
                'message' => 'User role updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user role assignment.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $userRole = UserRole::find($id);

            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role not found'
                ], 404);
            }

            $userRole->delete();

            return response()->json([
                'success' => true,
                'message' => 'User role assignment removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user role active status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $userRole = UserRole::find($id);

            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role not found'
                ], 404);
            }

            $userRole->update(['is_active' => !$userRole->is_active]);
            $userRole->load(['user', 'role', 'assignedBy']);

            return response()->json([
                'success' => true,
                'data' => $userRole,
                'message' => 'User role status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user role status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign roles to a user
     */
    public function bulkAssignToUser(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'user_id' => 'required|exists:xxx_employees,id',
                'role_ids' => 'required|array',
                'role_ids.*' => 'exists:xxx_roles,id',
                'assigned_by' => 'nullable|exists:xxx_employees,id'
            ]);

            $assignedBy = $validatedData['assigned_by'] ?? (Auth::check() ? Auth::id() : null);
            $userRoles = [];

            foreach ($validatedData['role_ids'] as $roleId) {
                // Check if assignment already exists
                $existing = UserRole::where('user_id', $validatedData['user_id'])
                                  ->where('role_id', $roleId)
                                  ->first();

                if (!$existing) {
                    $userRoles[] = UserRole::create([
                        'user_id' => $validatedData['user_id'],
                        'role_id' => $roleId,
                        'assigned_by' => $assignedBy,
                        'is_active' => true
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $userRoles,
                'message' => count($userRoles) . ' roles assigned successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error bulk assigning roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get roles for a specific user
     */
    public function getUserRoles($userId): JsonResponse
    {
        try {
            $user = Employee::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $userRoles = UserRole::with(['role', 'assignedBy'])
                               ->where('user_id', $userId)
                               ->orderBy('created_at', 'desc')
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $userRoles,
                'message' => 'User roles retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users for a specific role
     */
    public function getRoleUsers($roleId): JsonResponse
    {
        try {
            $role = Role::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $userRoles = UserRole::with(['user', 'assignedBy'])
                               ->where('role_id', $roleId)
                               ->orderBy('created_at', 'desc')
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $userRoles,
                'message' => 'Role users retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role users: ' . $e->getMessage()
            ], 500);
        }
    }
}
