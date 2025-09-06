<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::query();

            // Filter by active status if specified
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by name if specified
            if ($request->has('search')) {
                $query->where('name', 'like', '%'.$request->search.'%');
            }

            // Include relationships if requested
            if ($request->boolean('with_pages')) {
                $query->with('pages');
            }

            if ($request->boolean('with_users')) {
                $query->with('usersViaUserRoles');
            }

            if ($request->boolean('with_employees')) {
                $query->with('employees');
            }

            $roles = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
                'message' => 'Roles retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving roles: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:60|unique:xxx_roles,name',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            $role = Role::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => 'Role created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $query = Role::where('id', $id);

            // Include relationships if requested
            if ($request->boolean('with_pages')) {
                $query->with('pages');
            }

            if ($request->boolean('with_users')) {
                $query->with('usersViaUserRoles');
            }

            if ($request->boolean('with_employees')) {
                $query->with('employees');
            }

            $role = $query->first();

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => 'Role retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:60|unique:xxx_roles,name,'.$id,
                'description' => 'sometimes|nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
            ]);

            $role->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => $role->fresh(),
                'message' => 'Role updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Check if role has associated user roles
            if ($role->userRoles()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role with existing user assignments. Remove assignments first.',
                ], 422);
            }

            // Check if role has associated page permissions
            if ($role->pageRolePermissions()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role with existing page permissions. Remove permissions first.',
                ], 422);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting role: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle role active status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $role->update(['is_active' => ! $role->is_active]);

            return response()->json([
                'success' => true,
                'data' => $role->fresh(),
                'message' => 'Role status updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating role status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get users assigned to this role
     */
    public function getUsers($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $users = $role->usersViaUserRoles()
                ->with(['department', 'supervisor'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Role users retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role users: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pages accessible to this role
     */
    public function getPages($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $pages = $role->pages;

            return response()->json([
                'success' => true,
                'data' => $pages,
                'message' => 'Role pages retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role pages: '.$e->getMessage(),
            ], 500);
        }
    }
}
