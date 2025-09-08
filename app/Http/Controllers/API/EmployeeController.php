<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\EmpEmergContact;
use App\Models\Employee;
use App\Models\EmpPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
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

    /* Generate unique employee code with pattern EMP001, EMP002, etc. */
    private function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $lastEmployee = Employee::orderBy('id', 'desc')->first();

        if (! $lastEmployee) {
            return $prefix.'001'; // First employee
        }

        // If there's an existing code with the pattern, increment it
        $lastCode = $lastEmployee->employee_code;
        if (strpos($lastCode, $prefix) === 0) {
            $numPart = (int) substr($lastCode, strlen($prefix));
            $newNum = $numPart + 1;

            return $prefix.str_pad($newNum, 3, '0', STR_PAD_LEFT);
        }

        return $prefix.'001'; // Fallback if pattern doesn't match
    }

    /* ─────────────────────  Core rules  ───────────────────── */
    private function employeeRules(int $id = 0): array
    {
        return [
            /* ==== ALL COLUMNS IN xxx_employees ==== */
            'employee_code' => $id > 0 ?
                ['required', 'string', 'max:30', Rule::unique('xxx_employees', 'employee_code')->ignore($id)] :
                ['nullable', 'string', 'max:30'],
            'first_name' => 'required|string|max:60',
            'middle_name' => 'nullable|string|max:60',
            'last_name' => 'required|string|max:60',
            'qualification' => 'nullable|string|max:120',
            'nationality' => 'nullable|string|max:60',
            'region' => 'nullable|string|max:60',
            'address' => 'nullable|string|max:255',
            'work_email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    if (! str_contains($value, '@lightidea.org')) {
                        $fail('The work email must be a lightidea.org email address.');
                    }
                },
                Rule::unique('xxx_employees', 'work_email')->ignore($id),
            ],
            'personal_email' => 'nullable|email',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'military_status' => 'nullable|in:completed,exempted,postponed,not_applicable',
            'id_type' => 'required|in:national_id,passport,driving_license',
            'id_number' => 'required|string|max:60',
            'id_expiry_date' => 'required|date',
            'employee_type' => 'required|string',
            'job_title' => 'required|string|max:120',
            'designation' => 'nullable|string|max:120',
            'grade_level' => 'nullable|string|max:60',
            'department_id' => 'nullable|integer|exists:xxx_departments,id',
            'supervisor_id' => 'nullable|integer|exists:xxx_employees,id',
            'contract_start_date' => 'required|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'user_status' => 'nullable|in:active,inactive',
            'password' => 'nullable|string|min:8',
            'image_path' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240', // For file uploads
            'role_id' => 'nullable|integer|exists:xxx_roles,id',
            /* ==== CHILD COLLECTIONS ==== */
            'phones' => 'sometimes|array',
            'phones.*.phone_type' => 'required_with:phones|string|in:mobile,home,work,other',
            'phones.*.phone_number' => 'required_with:phones|string|max:30',
            'emergency_contacts' => 'sometimes|array',
            'emergency_contacts.*.name' => 'required_with:emergency_contacts|string|max:120',
            'emergency_contacts.*.relationship' => 'required_with:emergency_contacts|string|max:60',
            'emergency_contacts.*.phone' => 'required_with:emergency_contacts|string|max:30',
            'emergency_contacts.*.address' => 'nullable|string|max:255',
        ];
    }

    /* ─────────────────────  Index + Show  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with('role');

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('middle_name', 'LIKE', $searchTerm)
                    ->orWhere('employee_code', 'LIKE', $searchTerm)
                    ->orWhere('job_title', 'LIKE', $searchTerm)
                    ->orWhere('work_email', 'LIKE', $searchTerm)
                  // Handle full name searches (first + last)
                    ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", [$searchTerm])
                  // Handle full name searches (first + middle + last)
                    ->orWhereRaw("(first_name || ' ' || middle_name || ' ' || last_name) LIKE ?", [$searchTerm])
                  // Handle name variations (last + first)
                    ->orWhereRaw("(last_name || ' ' || first_name) LIKE ?", [$searchTerm]);
            });
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Apply status filter if provided
        if ($request->has('status')) {
            $query->where('user_status', $request->input('status'));
        }

        return $this->ok('Employees fetched successfully', $query->paginate($request->input('per_page', 5)));
    }

    public function show(int $id): JsonResponse
    {
        $e = Employee::with(['phones', 'emergencyContacts', 'role'])->find($id);

        return $e ? $this->ok('Employee fetched successfully', $e) : $this->fail('Employee not found', 404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->employeeRules());
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            /* Generate employee code */
            $requestData = $request->only((new Employee)->getFillable());
            $requestData['employee_code'] = $this->generateEmployeeCode();

            // Handle image upload
            if ($request->hasFile('image_path')) {
                $file = $request->file('image_path');
                $imageData = file_get_contents($file->getRealPath());

                // Validate image data
                if (! Employee::validateImageData($imageData)) {
                    return $this->fail('Invalid image data', 422);
                }

                // Ensure proper UTF-8 handling by encoding as base64
                $requestData['image_path'] = base64_encode($imageData);
            }

            $emp = Employee::create($requestData);

            /* phones */
            foreach ($request->input('phones', []) as $p) {
                $p['employee_id'] = $emp->id;
                EmpPhone::create($p);
            }
            /* emergency contacts */
            foreach ($request->input('emergency_contacts', []) as $c) {
                $c['employee_id'] = $emp->id;
                EmpEmergContact::create($c);
            }

            DB::commit();

            return $this->ok('Employee created successfully', $emp->load(['phones', 'emergencyContacts']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Log the actual error for debugging
            \Log::error('Error creating employee: '.$e->getMessage());

            return $this->fail('Error creating employee. Please check the data format.', 500);
        }
    }

    /* ─────────────────────  Update (POST)  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (! $emp) {
            return $this->fail('Employee not found', 404);
        }

        $v = Validator::make($request->all(), $this->employeeRules($id));
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            /* update scalar cols */
            $updateData = $request->only(array_diff($emp->getFillable(), ['image_path']));
            foreach ($updateData as $key => $value) {
                if ($request->filled($key)) {
                    // Ensure proper UTF-8 handling for string fields
                    if (is_string($value)) {
                        $emp->$key = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    } else {
                        $emp->$key = $value;
                    }
                }
            }

            // Handle image upload separately
            if ($request->hasFile('image_path')) {
                $file = $request->file('image_path');
                $imageData = file_get_contents($file->getRealPath());

                // Validate image data
                if (! Employee::validateImageData($imageData)) {
                    return $this->fail('Invalid image data', 422);
                }

                // Ensure proper UTF-8 handling by encoding as base64
                $emp->image_path = base64_encode($imageData);
            }

            $emp->save();

            /* ── sync phones: simple strategy → delete then re‑insert */
            EmpPhone::where('employee_id', $id)->delete();
            foreach ($request->input('phones', []) as $p) {
                $p['employee_id'] = $id;
                EmpPhone::create($p);
            }

            /* ── sync emergency contacts */
            EmpEmergContact::where('employee_id', $id)->delete();
            foreach ($request->input('emergency_contacts', []) as $c) {
                $c['employee_id'] = $id;
                EmpEmergContact::create($c);
            }

            DB::commit();

            return $this->ok('Employee updated successfully',
                $emp->load(['phones', 'emergencyContacts']));
        } catch (\Exception $e) {
            DB::rollBack();
            // Log the actual error for debugging
            \Log::error('Error updating employee: '.$e->getMessage());

            return $this->fail('Error updating employee. Please check the data format.', 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (! $emp) {
            return $this->fail('Employee not found', 404);
        }

        try {
            $emp->delete();             // FKs cascade to children

            return $this->ok('Employee deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting employee', 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non‑empty array', 422);
        }

        try {
            $deleted = Employee::whereIn('id', $ids)->delete();

            return $this->ok($deleted ? "$deleted employee(s) deleted successfully"
                                      : 'No employees were deleted');
        } catch (Throwable $e) {
            return $this->fail('Error deleting employees', 500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function search(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->select('id', 'first_name', 'last_name', 'job_title');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%'.$request->input('term').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('job_title', 'LIKE', $searchTerm);
            });
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Use EmployeeResource to ensure consistent formatting
        return $this->ok('Employee search results', EmployeeResource::collection($results));
    }

    /**
     * Return all employees without pagination (with the same filters as index).
     */
    public function all(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->select(['id', 'employee_code', 'first_name', 'last_name']);

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('middle_name', 'LIKE', $searchTerm)
                    ->orWhere('employee_code', 'LIKE', $searchTerm)
                    ->orWhere('job_title', 'LIKE', $searchTerm)
                    ->orWhere('work_email', 'LIKE', $searchTerm)
                    ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", [$searchTerm])
                    ->orWhereRaw("(first_name || ' ' || middle_name || ' ' || last_name) LIKE ?", [$searchTerm])
                    ->orWhereRaw("(last_name || ' ' || first_name) LIKE ?", [$searchTerm]);
            });
        }
        $employees = $query->get();

        return $this->ok('All employees fetched successfully', $employees);
    }

    /* ─────────────────────  Image Upload  ───────────────────── */

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        // Find the employee or return 404
        $emp = Employee::find($id);
        if (! $emp) {
            return $this->fail('Employee not found', 404);
        }

        // Validate that an image file was sent under "image"
        $validator = Validator::make(
            $request->all(),
            [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            ]
        );
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            // Read the uploaded file as binary data
            $file = $request->file('image');
            $imageData = file_get_contents($file->getRealPath());

            // Validate image data
            if (! Employee::validateImageData($imageData)) {
                return $this->fail('Invalid image data', 422);
            }

            // Ensure proper UTF-8 handling by encoding as base64
            $base64Data = base64_encode($imageData);

            // Validate the base64 encoding worked
            if (base64_decode($base64Data, true) === false) {
                return $this->fail('Failed to encode image data', 422);
            }

            $emp->image_path = $base64Data;
            $emp->save();

            // Return success with safe data
            return $this->ok('Employee image uploaded successfully', [
                'image_url' => $emp->image_url,
                'optimized_image_url' => $emp->optimized_image_url,
                'has_image' => $emp->hasImage(),
                'image_size' => $emp->getImageSize(),
                'mime_type' => $emp->getImageMimeType(),
            ]);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            \Log::error('Error uploading image: '.$e->getMessage());

            return $this->fail('Error uploading image. Please try again.', 500);
        }
    }

    /**
     * Get employee image
     */
    public function getImage(int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (! $emp) {
            return $this->fail('Employee not found', 404);
        }

        if (! $emp->hasImage()) {
            return $this->fail('Employee has no image', 404);
        }

        try {
            return $this->ok('Employee image retrieved successfully', [
                'image_url' => $emp->image_url,
                'optimized_image_url' => $emp->optimized_image_url,
                'image_base64' => $emp->image_base64,
                'image_size' => $emp->getImageSize(),
                'mime_type' => $emp->getImageMimeType(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving image: '.$e->getMessage());

            return $this->fail('Error retrieving image', 500);
        }
    }

    /**
     * Delete employee image
     */
    public function deleteImage(int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (! $emp) {
            return $this->fail('Employee not found', 404);
        }

        try {
            $emp->image_path = null;
            $emp->save();

            return $this->ok('Employee image deleted successfully', [
                'has_image' => $emp->hasImage(),
            ]);
        } catch (Throwable $e) {
            return $this->fail('Error deleting image: '.$e->getMessage(), 500);
        }
    }
}
