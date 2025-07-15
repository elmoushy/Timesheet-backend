<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\XxSupport;
use App\Models\SupportImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    /**
     * Display a listing of support records with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $employeeId = $request->get('employee_id');
            $hasImage = $request->get('has_image');
            $search = $request->get('search');
            $includeImageData = $request->get('include_image_data', false);

            // Only load image relationship if specifically requested
            $with = ['employee'];
            if ($includeImageData) {
                $with[] = 'supportImageWithData';
            } else {
                // Load only image metadata without actual image data
                $with[] = 'supportImage';
            }

            $query = XxSupport::with($with);

            // Filter by employee
            if ($employeeId) {
                $query->forEmployee($employeeId);
            }

            // Filter by image presence
            if ($hasImage === 'true') {
                $query->withImages();
            } elseif ($hasImage === 'false') {
                $query->withoutImages();
            }

            // Search in message
            if ($search) {
                $query->where('message', 'LIKE', '%' . $search . '%');
            }

            // Order by latest first
            $query->orderBy('created_at', 'desc');

            $supports = $query->paginate($perPage);

            // Transform the collection to include image metadata
            $supports->getCollection()->transform(function ($support) use ($includeImageData) {
                if ($support->supportImage) {
                    if ($includeImageData) {
                        // Only include image data URI if explicitly requested
                        $support->supportImage->image_url = $support->supportImage->getImageDataUri();
                    } else {
                        // Only include metadata
                        $support->supportImage = $support->supportImage->getImageMetadata();
                    }
                }
                return $support;
            });

            return response()->json([
                'message' => 'Support records fetched successfully',
                'data' => $supports
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Error retrieving support records: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving support records: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Display all support records without pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function all(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $search = $request->get('search');

            $query = XxSupport::with(['employee', 'supportImage']);

            // Filter by employee
            if ($employeeId) {
                $query->forEmployee($employeeId);
            }

            // Search in message
            if ($search) {
                $query->where('message', 'LIKE', '%' . $search . '%');
            }

            // Order by latest first
            $query->orderBy('created_at', 'desc');

            $supports = $query->get();

            // Add image URL to each support record
            $supports->transform(function ($support) {
                if ($support->supportImage) {
                    // Don't access image data, just ensure it's not accidentally included
                    unset($support->supportImage->image); // Ensure raw image data is not included
                }
                return $support;
            });

            return response()->json([
                'message' => 'All support records fetched successfully',
                'data' => $supports
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Error retrieving all support records: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving all support records: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Display the specified support record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $support = XxSupport::with(['employee', 'supportImage'])->find($id);

            if (!$support) {
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            // Add image URL if exists
            if ($support->supportImage) {
                // Don't access image data, just ensure it's not accidentally included
                unset($support->supportImage->image); // Ensure raw image data is not included
            }

            return response()->json([
                'message' => 'Support record fetched successfully',
                'data' => $support
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Error retrieving support record: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving support record: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Store a newly created support record.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Temporarily increase memory limit for image processing
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '256M');

            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required|string',
                'support_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'data' => []
                ], 422);
            }

            DB::beginTransaction();

            $supportImageId = null;

            // Handle image upload if provided
            if ($request->hasFile('support_image')) {
                $image = $request->file('support_image');

                // Validate image
                $imageData = file_get_contents($image->getPathname());
                $imageInfo = getimagesize($image->getPathname());

                if (!$imageInfo) {
                    return response()->json([
                        'message' => 'Invalid image data',
                        'data' => []
                    ], 422);
                }

                // Ensure we have valid binary data
                if (!$imageData || strlen($imageData) === 0) {
                    return response()->json([
                        'message' => 'Empty image data',
                        'data' => []
                    ], 422);
                }

                // Create support image record
                $supportImage = SupportImage::create([
                    'image' => $imageData,
                    'mime_type' => $imageInfo['mime'],
                    'size' => strlen($imageData),
                    'original_name' => $image->getClientOriginalName(),
                ]);

                $supportImageId = $supportImage->id;
            }

            // Create support record with authenticated user's ID
            $support = XxSupport::create([
                'employee_id' => $user->id,
                'message' => $request->message,
                'support_image_id' => $supportImageId,
            ]);

            // Load relationships with minimal data
            $support->load(['employee', 'supportImage:id,mime_type,size,original_name,created_at,updated_at']);

            // Transform supportImage to metadata only
            if ($support->supportImage) {
                $support->supportImage = $support->supportImage->getImageMetadata();
            }

            DB::commit();

            // Clean up memory
            unset($imageData, $supportImage);
            gc_collect_cycles();

            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            return response()->json([
                'message' => 'Support record created successfully',
                'data' => $support
            ], 201, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();

            // Restore original memory limit on error
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }

            Log::error('Error creating support record: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating support record: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Update the specified support record.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $support = XxSupport::find($id);

            if (!$support) {
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            // Check if the authenticated user owns this support record
            if ($support->employee_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized - you can only update your own support records',
                    'data' => []
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'sometimes|required|string',
                'support_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'data' => []
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            DB::beginTransaction();

            // Handle image upload if provided
            if ($request->hasFile('support_image')) {
                $image = $request->file('support_image');

                // Validate image
                $imageData = file_get_contents($image->getPathname());
                $imageInfo = getimagesize($image->getPathname());

                if (!$imageInfo) {
                    return response()->json([
                        'message' => 'Invalid image data',
                        'data' => []
                    ], 422, [], JSON_UNESCAPED_UNICODE);
                }

                // Delete old image if exists
                if ($support->support_image_id) {
                    $oldImage = SupportImage::find($support->support_image_id);
                    if ($oldImage) {
                        $oldImage->delete();
                    }
                }

                // Create new support image record
                $supportImage = SupportImage::create([
                    'image' => $imageData,
                    'mime_type' => $imageInfo['mime'],
                    'size' => strlen($imageData),
                    'original_name' => $image->getClientOriginalName(),
                ]);

                $support->support_image_id = $supportImage->id;
            }

            // Update support record (only message can be updated)
            $support->update($request->only(['message']));

            // Load relationships
            $support->load(['employee', 'supportImage']);

            // Remove raw image data from response
            if ($support->supportImage) {
                unset($support->supportImage->image);
            }

            DB::commit();

            return response()->json([
                'message' => 'Support record updated successfully',
                'data' => $support
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating support record: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating support record: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Remove the specified support record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $support = XxSupport::find($id);

            if (!$support) {
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            // Check if the authenticated user owns this support record
            if ($support->employee_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized - you can only delete your own support records',
                    'data' => []
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            DB::beginTransaction();

            // Delete associated image if exists
            if ($support->support_image_id) {
                $image = SupportImage::find($support->support_image_id);
                if ($image) {
                    $image->delete();
                }
            }

            // Delete support record
            $support->delete();

            DB::commit();

            return response()->json([
                'message' => 'Support record deleted successfully',
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting support record: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting support record',
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Bulk delete support records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:xx_support,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'data' => []
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            DB::beginTransaction();

            // Only allow deletion of user's own support records
            $supports = XxSupport::whereIn('id', $request->ids)
                              ->where('employee_id', $user->id)
                              ->get();

            if ($supports->count() !== count($request->ids)) {
                return response()->json([
                    'message' => 'Unauthorized - you can only delete your own support records',
                    'data' => []
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            // Delete associated images
            $imageIds = $supports->pluck('support_image_id')->filter();
            if ($imageIds->isNotEmpty()) {
                SupportImage::whereIn('id', $imageIds)->delete();
            }

            // Delete support records
            $deletedCount = XxSupport::whereIn('id', $request->ids)
                                   ->where('employee_id', $user->id)
                                   ->delete();

            DB::commit();

            return response()->json([
                'message' => "{$deletedCount} support record(s) deleted successfully",
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting support records: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting support records',
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Search support records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $term = $request->get('term', '');
            $employeeId = $request->get('employee_id');

            $query = XxSupport::with(['employee', 'supportImage']);

            if ($term) {
                $query->where('message', 'LIKE', '%' . $term . '%');
            }

            if ($employeeId) {
                $query->forEmployee($employeeId);
            }

            $supports = $query->orderBy('created_at', 'desc')
                           ->limit(20)
                           ->get();

            // Add image URL and format for display
            $supports->transform(function ($support) {
                if ($support->supportImage) {
                    // Don't access image_url here as it would load the binary data
                    // Only access has_image which is memory efficient
                    $support->supportImage->has_image = $support->supportImage->has_image;
                }

                // Add display text for search results
                $support->display_text = substr($support->message, 0, 100) .
                                       (strlen($support->message) > 100 ? '...' : '');

                return $support;
            });

            return response()->json([
                'message' => 'Support search results',
                'data' => $supports
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Error searching support records: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error searching support records: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Upload image for support record.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request, $id)
    {
        try {
            // Temporarily increase memory limit for image processing
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '512M');

            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                ini_set('memory_limit', $originalMemoryLimit);
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $support = XxSupport::find($id);

            if (!$support) {
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            // Check if the authenticated user owns this support record
            if ($support->employee_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized - you can only upload images to your own support records',
                    'data' => []
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            $validator = Validator::make($request->all(), [
                'support_image' => 'required|file|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'data' => []
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            DB::beginTransaction();

            $image = $request->file('support_image');

            // Validate image
            $imageData = file_get_contents($image->getPathname());
            $imageInfo = getimagesize($image->getPathname());

            if (!$imageInfo) {
                return response()->json([
                    'message' => 'Invalid image data',
                    'data' => []
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            // Delete old image if exists
            if ($support->support_image_id) {
                $oldImage = SupportImage::find($support->support_image_id);
                if ($oldImage) {
                    $oldImage->delete();
                }
            }

            // Create new support image record
            $supportImage = SupportImage::create([
                'image' => $imageData,
                'mime_type' => $imageInfo['mime'],
                'size' => strlen($imageData),
                'original_name' => $image->getClientOriginalName(),
            ]);

            // Update support record
            $support->support_image_id = $supportImage->id;
            $support->save();

            DB::commit();

            $responseData = [
                'image_url' => $supportImage->image_url,
                'has_image' => $supportImage->has_image,
                'image_size' => $supportImage->size,
                'mime_type' => $supportImage->mime_type,
                'original_name' => $supportImage->original_name,
            ];

            // Clean up memory
            unset($support, $supportImage, $imageData);
            gc_collect_cycles();
            ini_set('memory_limit', $originalMemoryLimit);

            return response()->json([
                'message' => 'Support image uploaded successfully',
                'data' => $responseData
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();
            // Ensure memory limit is restored even on error
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            Log::error('Error uploading support image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error uploading image: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get image data for a support record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImage($id)
    {
        try {
            // Temporarily increase memory limit for image processing
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '512M');

            $support = XxSupport::with('supportImageWithData')->find($id);

            if (!$support) {
                ini_set('memory_limit', $originalMemoryLimit);
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            if (!$support->supportImageWithData) {
                ini_set('memory_limit', $originalMemoryLimit);
                return response()->json([
                    'message' => 'No image found for this support record',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            $imageData = $support->supportImageWithData->getImageDataUri();

            if (!$imageData) {
                ini_set('memory_limit', $originalMemoryLimit);
                return response()->json([
                    'message' => 'Error retrieving image data',
                    'data' => []
                ], 500, [], JSON_UNESCAPED_UNICODE);
            }

            $metadata = $support->supportImageWithData->getImageMetadata();

            // Clean up memory
            unset($support);
            gc_collect_cycles();
            ini_set('memory_limit', $originalMemoryLimit);

            return response()->json([
                'message' => 'Image retrieved successfully',
                'data' => [
                    'image_url' => $imageData,
                    'metadata' => $metadata,
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            // Ensure memory limit is restored even on error
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            Log::error('Error retrieving support image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving support image: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Delete image for support record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage($id)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized - user not authenticated',
                    'data' => []
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $support = XxSupport::find($id);

            if (!$support) {
                return response()->json([
                    'message' => 'Support record not found',
                    'data' => []
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            // Check if the authenticated user owns this support record
            if ($support->employee_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized - you can only delete images from your own support records',
                    'data' => []
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            DB::beginTransaction();

            // Delete image if exists
            if ($support->support_image_id) {
                $image = SupportImage::find($support->support_image_id);
                if ($image) {
                    $image->delete();
                }

                // Remove image reference from support record
                $support->support_image_id = null;
                $support->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Support image deleted successfully',
                'data' => [
                    'has_image' => false
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting support image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting image: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
