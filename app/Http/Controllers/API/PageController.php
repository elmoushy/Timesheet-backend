<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    /**
     * Display a listing of the pages.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Page::query();

            // Filter by active status if specified
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by name if specified
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Include roles if requested
            if ($request->boolean('with_roles')) {
                $query->with('roles');
            }

            $pages = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $pages,
                'message' => 'Pages retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created page in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:150|unique:xxx_pages,name',
                'is_active' => 'boolean'
            ]);

            $page = Page::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => $page,
                'message' => 'Page created successfully'
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
                'message' => 'Error creating page: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified page.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $query = Page::where('id', $id);

            // Include roles if requested
            if ($request->boolean('with_roles')) {
                $query->with('roles');
            }

            $page = $query->first();

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $page,
                'message' => 'Page retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving page: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified page in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $page = Page::find($id);

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:150|unique:xxx_pages,name,' . $id,
                'is_active' => 'sometimes|boolean'
            ]);

            $page->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => $page->fresh(),
                'message' => 'Page updated successfully'
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
                'message' => 'Error updating page: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified page from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $page = Page::find($id);

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            // Check if page has associated permissions
            if ($page->pageRolePermissions()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete page with existing role permissions. Remove permissions first.'
                ], 422);
            }

            $page->delete();

            return response()->json([
                'success' => true,
                'message' => 'Page deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting page: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle page active status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $page = Page::find($id);

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            $page->update(['is_active' => !$page->is_active]);

            return response()->json([
                'success' => true,
                'data' => $page->fresh(),
                'message' => 'Page status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating page status: ' . $e->getMessage()
            ], 500);
        }
    }
}
