<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRolePermission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PageRolePermissionController extends Controller
{
    /**
     * Display a listing of page role permissions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PageRolePermission::with(['page', 'role']);

            // Filter by active status if specified
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by page if specified
            if ($request->has('page_id')) {
                $query->where('page_id', $request->page_id);
            }

            // Filter by role if specified
            if ($request->has('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            // Search by page name if specified
            if ($request->has('search')) {
                $query->whereHas('page', function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%');
                });
            }

            $permissions = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'message' => 'Page role permissions retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving page role permissions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created page role permission.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'page_id' => 'required|exists:xxx_pages,id',
                'role_id' => 'required|exists:xxx_roles,id',
                'is_active' => 'boolean',
            ]);

            // Check if the combination already exists
            $existingPermission = PageRolePermission::where('page_id', $validatedData['page_id'])
                ->where('role_id', $validatedData['role_id'])
                ->first();

            if ($existingPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page role permission already exists',
                ], 422);
            }

            $permission = PageRolePermission::create($validatedData);
            $permission->load(['page', 'role']);

            return response()->json([
                'success' => true,
                'data' => $permission,
                'message' => 'Page role permission created successfully',
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
                'message' => 'Error creating page role permission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified page role permission.
     */
    public function show($id): JsonResponse
    {
        try {
            $permission = PageRolePermission::with(['page', 'role'])->find($id);

            if (! $permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page role permission not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $permission,
                'message' => 'Page role permission retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving page role permission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified page role permission.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $permission = PageRolePermission::find($id);

            if (! $permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page role permission not found',
                ], 404);
            }

            $validatedData = $request->validate([
                'page_id' => 'sometimes|required|exists:xxx_pages,id',
                'role_id' => 'sometimes|required|exists:xxx_roles,id',
                'is_active' => 'sometimes|boolean',
            ]);

            // Check if the new combination already exists (excluding current record)
            if (isset($validatedData['page_id']) || isset($validatedData['role_id'])) {
                $pageId = $validatedData['page_id'] ?? $permission->page_id;
                $roleId = $validatedData['role_id'] ?? $permission->role_id;

                $existingPermission = PageRolePermission::where('page_id', $pageId)
                    ->where('role_id', $roleId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Page role permission already exists',
                    ], 422);
                }
            }

            $permission->update($validatedData);
            $permission->load(['page', 'role']);

            return response()->json([
                'success' => true,
                'data' => $permission,
                'message' => 'Page role permission updated successfully',
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
                'message' => 'Error updating page role permission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified page role permission.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $permission = PageRolePermission::find($id);

            if (! $permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page role permission not found',
                ], 404);
            }

            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Page role permission deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting page role permission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle page role permission active status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $permission = PageRolePermission::find($id);

            if (! $permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page role permission not found',
                ], 404);
            }

            $permission->update(['is_active' => ! $permission->is_active]);
            $permission->load(['page', 'role']);

            return response()->json([
                'success' => true,
                'data' => $permission,
                'message' => 'Page role permission status updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating permission status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk assign pages to a role
     */
    public function bulkAssignPagesToRole(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'role_id' => 'required|exists:xxx_roles,id',
                'page_ids' => 'required|array',
                'page_ids.*' => 'exists:xxx_pages,id',
            ]);

            $permissions = [];

            foreach ($validatedData['page_ids'] as $pageId) {
                // Check if permission already exists
                $existing = PageRolePermission::where('role_id', $validatedData['role_id'])
                    ->where('page_id', $pageId)
                    ->first();

                if (! $existing) {
                    $permissions[] = PageRolePermission::create([
                        'role_id' => $validatedData['role_id'],
                        'page_id' => $pageId,
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'message' => count($permissions).' page permissions assigned successfully',
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
                'message' => 'Error bulk assigning permissions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permissions for a specific page
     */
    public function getPagePermissions($pageId): JsonResponse
    {
        try {
            $page = Page::find($pageId);

            if (! $page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found',
                ], 404);
            }

            $permissions = PageRolePermission::with(['role'])
                ->where('page_id', $pageId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'message' => 'Page permissions retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving page permissions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions($roleId): JsonResponse
    {
        try {
            $role = Role::find($roleId);

            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $permissions = PageRolePermission::with(['page'])
                ->where('role_id', $roleId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'message' => 'Role permissions retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role permissions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permission matrix (pages vs roles)
     */
    public function getPermissionMatrix(): JsonResponse
    {
        try {
            $pages = Page::active()->orderBy('name')->get();
            $roles = Role::active()->orderBy('name')->get();

            $permissions = PageRolePermission::active()
                ->with(['page', 'role'])
                ->get()
                ->groupBy('page_id');

            $matrix = [];

            foreach ($pages as $page) {
                $pagePermissions = $permissions->get($page->id, collect());
                $rolePermissions = [];

                foreach ($roles as $role) {
                    $hasPermission = $pagePermissions->where('role_id', $role->id)->isNotEmpty();
                    $rolePermissions[$role->id] = $hasPermission;
                }

                $matrix[] = [
                    'page' => $page,
                    'role_permissions' => $rolePermissions,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'matrix' => $matrix,
                ],
                'message' => 'Permission matrix retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving permission matrix: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create multiple page role permissions
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'permissions' => 'required|array',
                'permissions.*.page_id' => 'required|exists:xxx_pages,id',
                'permissions.*.role_id' => 'required|exists:xxx_roles,id',
                'permissions.*.is_active' => 'boolean',
            ]);

            $createdPermissions = [];
            $skippedPermissions = [];

            foreach ($validatedData['permissions'] as $permissionData) {
                // Check if the combination already exists
                $existing = PageRolePermission::where('page_id', $permissionData['page_id'])
                    ->where('role_id', $permissionData['role_id'])
                    ->first();

                if (! $existing) {
                    $permission = PageRolePermission::create([
                        'page_id' => $permissionData['page_id'],
                        'role_id' => $permissionData['role_id'],
                        'is_active' => $permissionData['is_active'] ?? true,
                    ]);
                    $permission->load(['page', 'role']);
                    $createdPermissions[] = $permission;
                } else {
                    $skippedPermissions[] = [
                        'page_id' => $permissionData['page_id'],
                        'role_id' => $permissionData['role_id'],
                        'reason' => 'Permission already exists',
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'created' => $createdPermissions,
                    'skipped' => $skippedPermissions,
                ],
                'message' => count($createdPermissions).' permission(s) created successfully, '.count($skippedPermissions).' skipped',
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
                'message' => 'Error bulk creating permissions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete multiple page role permissions
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:xxx_page_role_permissions,id',
            ]);

            $deletedCount = 0;
            $notFoundIds = [];

            foreach ($validatedData['ids'] as $id) {
                $permission = PageRolePermission::find($id);
                if ($permission) {
                    $permission->delete();
                    $deletedCount++;
                } else {
                    $notFoundIds[] = $id;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'not_found_ids' => $notFoundIds,
                ],
                'message' => $deletedCount.' permission(s) deleted successfully',
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
                'message' => 'Error bulk deleting permissions: '.$e->getMessage(),
            ], 500);
        }
    }
}
