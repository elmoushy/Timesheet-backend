<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Client;
use App\Models\AssignedTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class DepartmentController extends Controller
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

    /* ─────────────────────  Core rules  ───────────────────── */
    private function departmentRules(int $id = 0): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $id > 0 ? Rule::unique('xxx_departments', 'name')->ignore($id) : Rule::unique('xxx_departments', 'name')
            ],
            'notes' => 'nullable|string',
            'managers' => 'sometimes|array',
            'managers.*.employee_id' => 'required_with:managers|integer|exists:xxx_employees,id',
            'managers.*.is_primary' => 'required_with:managers|boolean',
            'managers.*.start_date' => 'required_with:managers|date',
            'managers.*.end_date' => 'nullable|date|after_or_equal:managers.*.start_date',
        ];
    }

    /* ─────────────────────  Index + Show  ───────────────────── */
    public function index(): JsonResponse
    {
        return $this->ok('Departments fetched successfully', Department::with('managers')->paginate(10));
    }

    public function show(int $id): JsonResponse
    {
        $department = Department::with(['managers', 'employees', 'projects'])->find($id);
        return $department
            ? $this->ok('Department fetched successfully', $department)
            : $this->fail('Department not found', 404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->departmentRules());
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Create department
            $department = Department::create($request->only(['name', 'notes']));

            // Add managers if provided
            if ($request->has('managers')) {
                $this->syncManagers($department, $request->input('managers'));
            }

            DB::commit();
            return $this->ok(
                'Department created successfully',
                $department->load('managers'),
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error creating department: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::find($id);
        if (!$department) {
            return $this->fail('Department not found', 404);
        }

        $v = Validator::make($request->all(), $this->departmentRules($id));
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Update department
            $department->fill($request->only(['name', 'notes']));
            $department->save();

            // Update managers if provided
            if ($request->has('managers')) {
                $this->syncManagers($department, $request->input('managers'));
            }

            DB::commit();
            return $this->ok(
                'Department updated successfully',
                $department->load('managers')
            );
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error updating department: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $department = Department::find($id);
        if (!$department) {
            return $this->fail('Department not found', 404);
        }

        try {
            $department->delete(); // This will cascade to department managers through foreign keys
            return $this->ok('Department deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting department: ' . $e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non-empty array', 422);
        }

        try {
            $deleted = Department::whereIn('id', $ids)->delete();
            return $this->ok($deleted
                ? "$deleted department(s) deleted successfully"
                : 'No departments were deleted'
            );
        } catch (Throwable $e) {
            return $this->fail('Error deleting departments: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Department Managers  ───────────────────── */
    private function syncManagers(Department $department, array $managersData): void
    {
        // Remove all existing managers
        $department->managers()->detach();

        // Add new managers
        foreach ($managersData as $managerData) {
            $department->managers()->attach($managerData['employee_id'], [
                'is_primary' => $managerData['is_primary'] ?? false,
                'start_date' => $managerData['start_date'],
                'end_date' => $managerData['end_date'] ?? null,
            ]);
        }
    }

    public function addManager(Request $request, int $id): JsonResponse
    {
        $department = Department::find($id);
        if (!$department) {
            return $this->fail('Department not found', 404);
        }

        $rules = [
            'employee_id' => 'required|integer|exists:xxx_employees,id',
            'is_primary' => 'boolean',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            // If this is a primary manager, update other managers
            if ($request->input('is_primary', false)) {
                DB::table('xxx_department_managers')
                    ->where('department_id', $id)
                    ->update(['is_primary' => false]);
            }

            $department->managers()->attach($request->input('employee_id'), [
                'is_primary' => $request->input('is_primary', false),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ]);

            return $this->ok(
                'Department manager added successfully',
                $department->load('managers')
            );
        } catch (Throwable $e) {
            return $this->fail('Error adding department manager: ' . $e->getMessage(), 500);
        }
    }

    public function removeManager(int $departmentId, int $employeeId): JsonResponse
    {
        $department = Department::find($departmentId);
        if (!$department) {
            return $this->fail('Department not found', 404);
        }

        try {
            $removed = $department->managers()->detach($employeeId);

            return $removed
                ? $this->ok('Department manager removed successfully', $department->load('managers'))
                : $this->fail('Manager not found in this department', 404);
        } catch (Throwable $e) {
            return $this->fail('Error removing department manager: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function search(Request $request): JsonResponse
    {
        $query = Department::query()
            ->select('id', 'name');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%' . $request->input('term') . '%';
            $query->where('name', 'LIKE', $searchTerm);
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'display_text' => $department->name
            ];
        });

        return $this->ok('Department search results', $formattedResults);
    }

    public function clientdropdown(Request $request): JsonResponse
    {
        $query = Client::query()
            ->select('id', 'name', 'alias', 'region');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%' . $request->input('term') . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                  ->orWhere('alias', 'LIKE', $searchTerm)
                  ->orWhere('region', 'LIKE', $searchTerm);
            });
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'alias' => $client->alias,
                'region' => $client->region,
                'display_text' => $client->name . ($client->alias ? ' (' . $client->alias . ')' : '')
            ];
        });

        return $this->ok('Client search results', $formattedResults);
    }

    /* ────  ───────────── ──  Employee Assigned Tasks  ───────────────────── */
    public function getEmployeeAssignedTasks(int $employeeId): JsonResponse
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return $this->fail('Employee not found', 404);
        }

        try {
            $assignedTasks = AssignedTask::where('assigned_to', $employeeId)
                ->with(['task', 'assignedBy'])
                ->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            return $this->ok(
                'Employee assigned tasks fetched successfully',
                $assignedTasks
            );
        } catch (Throwable $e) {
            return $this->fail('Error fetching assigned tasks: ' . $e->getMessage(), 500);
        }
    }
}
