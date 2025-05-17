<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmpPhone;
use App\Models\EmpEmergContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        if (!$lastEmployee) {
            return $prefix . '001'; // First employee
        }

        // If there's an existing code with the pattern, increment it
        $lastCode = $lastEmployee->employee_code;
        if (strpos($lastCode, $prefix) === 0) {
            $numPart = (int)substr($lastCode, strlen($prefix));
            $newNum = $numPart + 1;
            return $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        }

        return $prefix . '001'; // Fallback if pattern doesn't match
    }

    /* ─────────────────────  Core rules  ───────────────────── */
    private function employeeRules(int $id = 0): array
    {
        return [
            /* ==== ALL COLUMNS IN xxx_employees ==== */
            'employee_code'       => $id > 0 ?
                ['required','string','max:30', Rule::unique('xxx_employees','employee_code')->ignore($id)] :
                ['nullable','string','max:30'],
            'first_name'          => 'required|string|max:60',
            'middle_name'         => 'nullable|string|max:60',
            'last_name'           => 'required|string|max:60',
            'qualification'       => 'nullable|string|max:120',
            'nationality'         => 'nullable|string|max:60',
            'region'              => 'nullable|string|max:60',
            'address'             => 'nullable|string|max:255',
            'work_email'          => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    if (!str_contains($value, '@lightidea.org')) {
                        $fail('The work email must be a lightidea.org email address.');
                    }
                },
                Rule::unique('xxx_employees','work_email')->ignore($id)
            ],
            'personal_email'      => 'nullable|email',
            'birth_date'          => 'required|date',
            'gender'              => 'required|in:male,female',
            'marital_status'      => 'required|in:single,married,divorced,widowed',
            'military_status'     => 'nullable|in:completed,exempted,postponed,not_applicable',
            'id_type'             => 'required|in:national_id,passport,driving_license',
            'id_number'           => 'required|string|max:60',
            'id_expiry_date'      => 'required|date',
            'employee_type'       => 'required|in:full_time,part_time,contractor,intern',
            'job_title'           => 'required|string|max:120',
            'designation'         => 'nullable|string|max:120',
            'grade_level'         => 'nullable|string|max:60',
            'department_id'       => 'nullable|integer|exists:xxx_departments,id',
            'supervisor_id'       => 'nullable|integer|exists:xxx_employees,id',
            'contract_start_date' => 'required|date',
            'contract_end_date'   => 'nullable|date|after_or_equal:contract_start_date',
            'user_status'         => 'nullable|in:active,inactive',
            'password'            => 'nullable|string|min:8',
            'image_path'          => 'nullable|string|max:255',
            'role_id'             => 'nullable|integer|exists:xxx_roles,id',
            /* ==== CHILD COLLECTIONS ==== */
            'phones'                    => 'sometimes|array',
            'phones.*.phone_type'       => 'required_with:phones|string|in:mobile,home,work,other',
            'phones.*.phone_number'     => 'required_with:phones|string|max:30',
            'emergency_contacts'                => 'sometimes|array',
            'emergency_contacts.*.name'         => 'required_with:emergency_contacts|string|max:120',
            'emergency_contacts.*.relationship' => 'required_with:emergency_contacts|string|max:60',
            'emergency_contacts.*.phone'        => 'required_with:emergency_contacts|string|max:30',
            'emergency_contacts.*.address'      => 'nullable|string|max:255',
        ];
    }

    /* ─────────────────────  Index + Show  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with('role');

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%' . $request->input('search') . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                  ->orWhere('last_name', 'LIKE', $searchTerm)
                  ->orWhere('middle_name', 'LIKE', $searchTerm)
                  ->orWhere('employee_code', 'LIKE', $searchTerm)
                  ->orWhere('job_title', 'LIKE', $searchTerm)
                  ->orWhere('work_email', 'LIKE', $searchTerm)
                  // Handle full name searches (first + last)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm])
                  // Handle full name searches (first + middle + last)
                  ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", [$searchTerm])
                  // Handle name variations (last + first)
                  ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", [$searchTerm]);
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

        return $this->ok('Employees fetched successfully', $query->paginate($request->input('per_page', 10)));
    }
    public function show(int $id): JsonResponse
    {
        $e = Employee::with(['phones','emergencyContacts','role'])->find($id);
        return $e ? $this->ok('Employee fetched successfully', $e) : $this->fail('Employee not found',404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->employeeRules());
        if ($v->fails()) { return $this->fail($v->errors()->first(),422); }

        DB::beginTransaction();
        try {
            /* Generate employee code */
            $requestData = $request->only((new Employee)->getFillable());
            $requestData['employee_code'] = $this->generateEmployeeCode();

            // Handle image upload if present
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'employee_new_' . time() . '.' . $file->getClientOriginalExtension();
                $fileContents = file_get_contents($file->getRealPath());
                Storage::disk('employee_photos')->put($filename, $fileContents);
                $requestData['image_path'] = url('/storage/employee_photos/' . $filename);
            }

            /* create employee */
            $emp = Employee::create($requestData);

            /* phones */
            foreach ($request->input('phones',[]) as $p) {
                $p['employee_id'] = $emp->id;
                EmpPhone::create($p);
            }
            /* emergency contacts */
            foreach ($request->input('emergency_contacts',[]) as $c) {
                $c['employee_id'] = $emp->id;
                EmpEmergContact::create($c);
            }

            DB::commit();
            return $this->ok('Employee created successfully', $emp->load(['phones','emergencyContacts']),201);
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error creating employee: ' . $e->getMessage(),500);
        }
    }

    /* ─────────────────────  Update (POST)  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (!$emp) { return $this->fail('Employee not found',404); }

        $v = Validator::make($request->all(), $this->employeeRules($id));
        if ($v->fails()) { return $this->fail($v->errors()->first(),422); }

        DB::beginTransaction();
        try {
            /* update scalar cols */
            foreach ($emp->getFillable() as $col) {
                if ($request->filled($col)) { $emp->$col = $request->$col; }
            }

            // Handle image upload if present
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($emp->image_path) {
                    $oldFilename = basename(parse_url($emp->image_path, PHP_URL_PATH));
                    if (Storage::disk('employee_photos')->exists($oldFilename)) {
                        Storage::disk('employee_photos')->delete($oldFilename);
                    }
                }

                // Store new image
                $file = $request->file('image');
                $filename = 'employee_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $fileContents = file_get_contents($file->getRealPath());
                Storage::disk('employee_photos')->put($filename, $fileContents);
                $emp->image_path = url('/storage/employee_photos/' . $filename);
            }

            $emp->save();

            /* ── sync phones: simple strategy → delete then re‑insert */
            EmpPhone::where('employee_id',$id)->delete();
            foreach ($request->input('phones',[]) as $p) {
                $p['employee_id'] = $id;
                EmpPhone::create($p);
            }

            /* ── sync emergency contacts */
            EmpEmergContact::where('employee_id',$id)->delete();
            foreach ($request->input('emergency_contacts',[]) as $c) {
                $c['employee_id'] = $id;
                EmpEmergContact::create($c);
            }

            DB::commit();
            return $this->ok('Employee updated successfully',
                $emp->load(['phones','emergencyContacts']));
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error updating employee: ' . $e->getMessage(),500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (!$emp) { return $this->fail('Employee not found',404); }

        try {
            $emp->delete();             // FKs cascade to children
            return $this->ok('Employee deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting employee',500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids',[]);
        if (!is_array($ids)||empty($ids)) { return $this->fail('ids must be a non‑empty array',422); }

        try {
            $deleted = Employee::whereIn('id',$ids)->delete();
            return $this->ok($deleted ? "$deleted employee(s) deleted successfully"
                                      : 'No employees were deleted');
        } catch (Throwable $e) {
            return $this->fail('Error deleting employees',500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function search(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->select('id', 'first_name', 'last_name', 'job_title');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%' . $request->input('term') . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                  ->orWhere('last_name', 'LIKE', $searchTerm)
                  ->orWhere('job_title', 'LIKE', $searchTerm);
            });
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function($employee) {
            return [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'job_title' => $employee->job_title,
                'display_text' => $employee->first_name . ' ' . $employee->last_name . ' (' . $employee->job_title . ')'
            ];
        });

        return $this->ok('Employee search results', $formattedResults);
    }

    /* ─────────────────────  Image Upload  ───────────────────── */
    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $emp = Employee::find($id);
        if (!$emp) { return $this->fail('Employee not found', 404); }

        if (!$request->hasFile('image')) {
            return $this->fail('No image provided', 422);
        }

        $file = $request->file('image');

        // Validate the file
        $validator = Validator::make(['image' => $file], [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            // Delete old image if exists
            if ($emp->image_path) {
                // Extract filename from the full URL if needed
                $oldFilename = basename(parse_url($emp->image_path, PHP_URL_PATH));
                if (Storage::disk('employee_photos')->exists($oldFilename)) {
                    Storage::disk('employee_photos')->delete($oldFilename);
                }
            }

            // Generate unique filename
            $filename = 'employee_' . $emp->id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $fileContents = file_get_contents($file->getRealPath());
            Storage::disk('employee_photos')->put($filename, $fileContents);

            // Generate full URL with server name
            $fullUrl = url('/storage/employee_photos/' . $filename);

            // Update employee record with full image URL
            $emp->image_path = $fullUrl;
            $emp->save();

            return $this->ok('Employee image uploaded successfully', [
                'image_path' => $fullUrl
            ]);
        } catch (Throwable $e) {
            return $this->fail('Error uploading image: ' . $e->getMessage(), 500);
        }
    }
}
