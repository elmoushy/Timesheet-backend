<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SupportImage;
use App\Models\XxSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SupportController extends Controller
{
    /* ─────────────────────  Helpers  ───────────────────── */
    private function ok(string $msg, $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => $data], $code);
    }

    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => []], $code);
    }

    /**
     * Display a listing of support records with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 10);
            $employeeId = $request->input('employee_id');
            $hasImage = $request->input('has_image');
            $search = $request->input('search');
            $includeImageData = $request->input('include_image_data', false);

            $query = XxSupport::with([
                'employee:id,employee_code,first_name,middle_name,last_name', // Limited employee fields
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ]);

            // Apply filters
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            if ($hasImage !== null) {
                if ($hasImage === 'true' || $hasImage === '1') {
                    $query->whereNotNull('support_image_id');
                } else {
                    $query->whereNull('support_image_id');
                }
            }

            if ($search) {
                $query->where('message', 'like', '%'.$search.'%');
            }

            $supports = $query->latest()->paginate($perPage);

            // Add image data if requested
            if ($includeImageData) {
                $supports->getCollection()->transform(function ($support) {
                    if ($support->supportImage) {
                        $support->supportImage->has_image = true;
                        $support->supportImage->image_url = $support->supportImage->getImageUrl();
                    }

                    return $support;
                });
            } else {
                $supports->getCollection()->transform(function ($support) {
                    if ($support->supportImage) {
                        $support->supportImage->has_image = true;
                        $support->supportImage->makeHidden(['image_data']);
                    }

                    return $support;
                });
            }

            return $this->ok('Support records fetched successfully', $supports);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch support records: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display all support records without pagination.
     */
    public function all(Request $request): JsonResponse
    {
        try {
            $query = XxSupport::with([
                'employee:id,employee_code,first_name,middle_name,last_name', // Limited employee fields
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ]);

            $supports = $query->latest()->get();

            // Add image indicators without full data
            $supports->transform(function ($support) {
                if ($support->supportImage) {
                    $support->supportImage->has_image = true;
                    $support->supportImage->makeHidden(['image_data']);
                }

                return $support;
            });

            return $this->ok('All support records fetched successfully', $supports);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch all support records: '.$e->getMessage(), 500);
        }
    }

    /**
     * Search support records.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', '');
            $perPage = $request->input('per_page', 10);

            $query = XxSupport::with([
                'employee:id,employee_code,first_name,middle_name,last_name', // Limited employee fields
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ]);

            if ($search) {
                $query->where('message', 'like', '%'.$search.'%')
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('employee_code', 'like', '%'.$search.'%');
                    });
            }

            $supports = $query->latest()->paginate($perPage);

            // Add image indicators without full data
            $supports->getCollection()->transform(function ($support) {
                if ($support->supportImage) {
                    $support->supportImage->has_image = true;
                    $support->supportImage->makeHidden(['image_data']);
                }

                return $support;
            });

            return $this->ok('Support records search completed', $supports);
        } catch (Throwable $e) {
            return $this->fail('Failed to search support records: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified support record.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $support = XxSupport::with([
                'employee:id,employee_code,first_name,middle_name,last_name', // Limited employee fields only
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ])->find($id);

            if (! $support) {
                return $this->fail('Support record not found', 404);
            }

            // Add image data if support image exists
            if ($support->supportImage) {
                $support->supportImage->has_image = true;
                $support->supportImage->image_url = $support->supportImage->getImageUrl();
            }

            return $this->ok('Support record fetched successfully', $support);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch support record: '.$e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created support record.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'nullable|integer|exists:xxx_employees,id',
                'message' => 'required|string|max:1000',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return $this->fail('Validation failed: '.implode(', ', $validator->errors()->all()), 422);
            }

            DB::beginTransaction();

            $supportImageId = null;

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $supportImage = SupportImage::createFromUploadedFile($request->file('image'));
                $supportImageId = $supportImage->id;
            }

            // Get employee ID from request or authenticated user
            $employeeId = $request->employee_id ?? auth()->user()->id;

            $support = XxSupport::create([
                'employee_id' => $employeeId,
                'message' => $request->message,
                'support_image_id' => $supportImageId,
            ]);

            DB::commit();

            // Load relationships with limited employee data
            $support->load([
                'employee:id,employee_code,first_name,middle_name,last_name',
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ]);

            if ($support->supportImage) {
                $support->supportImage->has_image = true;
                $support->supportImage->image_url = $support->supportImage->getImageUrl();
            }

            return $this->ok('Support record created successfully', $support, 201);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to create support record: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update the specified support record.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $support = XxSupport::find($id);
            if (! $support) {
                return $this->fail('Support record not found', 404);
            }

            $validator = Validator::make($request->all(), [
                'employee_id' => 'nullable|integer|exists:xxx_employees,id',
                'message' => 'required|string|max:1000',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->fail('Validation failed: '.implode(', ', $validator->errors()->all()), 422);
            }

            DB::beginTransaction();

            $supportImageId = $support->support_image_id;

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($supportImageId) {
                    $oldImage = SupportImage::find($supportImageId);
                    if ($oldImage) {
                        $oldImage->delete();
                    }
                }

                $supportImage = SupportImage::createFromUploadedFile($request->file('image'));
                $supportImageId = $supportImage->id;
            }

            // Get employee ID from request or authenticated user
            $employeeId = $request->employee_id ?? auth()->user()->id;

            $support->update([
                'employee_id' => $employeeId,
                'message' => $request->message,
                'support_image_id' => $supportImageId,
            ]);

            DB::commit();

            // Load relationships with limited employee data
            $support->load([
                'employee:id,employee_code,first_name,middle_name,last_name',
                'supportImage:id,mime_type,size,original_name,created_at,updated_at',
            ]);

            if ($support->supportImage) {
                $support->supportImage->has_image = true;
                $support->supportImage->image_url = $support->supportImage->getImageUrl();
            }

            return $this->ok('Support record updated successfully', $support);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to update support record: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified support record.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $support = XxSupport::find($id);
            if (! $support) {
                return $this->fail('Support record not found', 404);
            }

            DB::beginTransaction();

            // Delete associated image if exists
            if ($support->support_image_id) {
                $supportImage = SupportImage::find($support->support_image_id);
                if ($supportImage) {
                    $supportImage->delete();
                }
            }

            $support->delete();

            DB::commit();

            return $this->ok('Support record deleted successfully');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to delete support record: '.$e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete support records.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:xx_support,id',
            ]);

            if ($validator->fails()) {
                return $this->fail('Validation failed: '.implode(', ', $validator->errors()->all()), 422);
            }

            DB::beginTransaction();

            $supports = XxSupport::whereIn('id', $request->ids)->get();
            $deletedCount = 0;

            foreach ($supports as $support) {
                // Delete associated image if exists
                if ($support->support_image_id) {
                    $supportImage = SupportImage::find($support->support_image_id);
                    if ($supportImage) {
                        $supportImage->delete();
                    }
                }

                $support->delete();
                $deletedCount++;
            }

            DB::commit();

            return $this->ok("Successfully deleted {$deletedCount} support records", ['deleted_count' => $deletedCount]);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to bulk delete support records: '.$e->getMessage(), 500);
        }
    }

    /**
     * Upload image for support record.
     */
    public function uploadImage(Request $request, string $id): JsonResponse
    {
        try {
            $support = XxSupport::find($id);
            if (! $support) {
                return $this->fail('Support record not found', 404);
            }

            $validator = Validator::make($request->all(), [
                'image' => 'required|file|mimes:jpeg,png,jpg,gif|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->fail('Validation failed: '.implode(', ', $validator->errors()->all()), 422);
            }

            DB::beginTransaction();

            // Delete old image if exists
            if ($support->support_image_id) {
                $oldImage = SupportImage::find($support->support_image_id);
                if ($oldImage) {
                    $oldImage->delete();
                }
            }

            $supportImage = SupportImage::createFromUploadedFile($request->file('image'));
            $support->update(['support_image_id' => $supportImage->id]);

            DB::commit();

            return $this->ok('Image uploaded successfully', [
                'image_id' => $supportImage->id,
                'image_url' => $supportImage->getImageUrl(),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to upload image: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get image for support record.
     */
    public function getImage(string $id): JsonResponse
    {
        try {
            $support = XxSupport::find($id);
            if (! $support || ! $support->support_image_id) {
                return $this->fail('Support record or image not found', 404);
            }

            $supportImage = SupportImage::find($support->support_image_id);
            if (! $supportImage) {
                return $this->fail('Support image not found', 404);
            }

            return $this->ok('Support image fetched successfully', [
                'id' => $supportImage->id,
                'mime_type' => $supportImage->mime_type,
                'size' => $supportImage->size,
                'original_name' => $supportImage->original_name,
                'image_url' => $supportImage->getImageUrl(),
                'created_at' => $supportImage->created_at,
                'updated_at' => $supportImage->updated_at,
            ]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch support image: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete image for support record.
     */
    public function deleteImage(string $id): JsonResponse
    {
        try {
            $support = XxSupport::find($id);
            if (! $support || ! $support->support_image_id) {
                return $this->fail('Support record or image not found', 404);
            }

            DB::beginTransaction();

            $supportImage = SupportImage::find($support->support_image_id);
            if ($supportImage) {
                $supportImage->delete();
            }

            $support->update(['support_image_id' => null]);

            DB::commit();

            return $this->ok('Support image deleted successfully');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Failed to delete support image: '.$e->getMessage(), 500);
        }
    }
}
