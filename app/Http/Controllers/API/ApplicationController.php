<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ApplicationController extends Controller
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
    private function applicationRules(int $id = 0): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $id > 0 ? Rule::unique('xxx_applications', 'name')->ignore($id) : Rule::unique('xxx_applications', 'name')
            ],
            'department_id' => 'nullable|integer|exists:xxx_departments,id',
            'employees' => 'sometimes|array',
            'employees.*' => 'integer|exists:xxx_employees,id',
        ];
    }

    /* ─────────────────────  Index & List  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        $query = Application::with('department');

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%' . $request->input('search') . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm);
            });
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        return $this->ok(
            'Applications fetched successfully',
            $query->paginate($request->input('per_page', 10))
        );
    }

    public function list(): JsonResponse
    {
        // Return all applications for dropdown/select lists (no pagination)
        $applications = Application::select('id', 'name')->orderBy('name')->get();
        return $this->ok('All applications fetched successfully', $applications);
    }

    /**
     * Get departments list for dropdown
     */
    public function departmentList(Request $request): JsonResponse
    {
        $query = Department::query()
            ->select('id', 'name');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%' . $request->input('term') . '%';
            $query->where('name', 'LIKE', $searchTerm);
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->get();

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

    /* ─────────────────────  Show  ───────────────────── */
    public function show($id): JsonResponse
    {
        $application = Application::with(['department', 'employees'])->find($id);
        return $application
            ? $this->ok('Application fetched successfully', $application)
            : $this->fail('Application not found', 404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->applicationRules());
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            // Create application
            $application = Application::create($request->only([
                'name', 'department_id'
            ]));

            // Sync employees if provided
            if ($request->has('employees')) {
                $application->employees()->sync($request->input('employees'));
            }

            return $this->ok(
                'Application created successfully',
                $application->load(['department', 'employees']),
                201
            );
        } catch (Throwable $e) {
            return $this->fail('Error creating application: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update  ───────────────────── */
    public function update(Request $request, $id): JsonResponse
    {
        $application = Application::find($id);
        if (!$application) {
            return $this->fail('Application not found', 404);
        }

        $v = Validator::make($request->all(), $this->applicationRules($id));
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            // Update application
            $application->update($request->only([
                'name', 'department_id'
            ]));

            // Sync employees if provided
            if ($request->has('employees')) {
                $application->employees()->sync($request->input('employees'));
            }

            return $this->ok(
                'Application updated successfully',
                $application->load(['department', 'employees'])
            );
        } catch (Throwable $e) {
            return $this->fail('Error updating application: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy($id): JsonResponse
    {
        $application = Application::find($id);
        if (!$application) {
            return $this->fail('Application not found', 404);
        }

        try {
            // Detach all employees first
            $application->employees()->detach();

            // Delete the application
            $application->delete();
            return $this->ok('Application deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting application: ' . $e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non-empty array', 422);
        }

        try {
            // Detach employees from all applications being deleted
            foreach (Application::whereIn('id', $ids)->get() as $application) {
                $application->employees()->detach();
            }

            $deleted = Application::whereIn('id', $ids)->delete();
            return $this->ok($deleted
                ? "$deleted application(s) deleted successfully"
                : 'No applications were deleted'
            );
        } catch (Throwable $e) {
            return $this->fail('Error deleting applications: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function search(Request $request): JsonResponse
    {
        $query = Application::query()
            ->select('id', 'name');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%' . $request->input('term') . '%';
            $query->where('name', 'LIKE', $searchTerm);
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function($application) {
            return [
                'id' => $application->id,
                'name' => $application->name,
                'display_text' => $application->name
            ];
        });

        return $this->ok('Application search results', $formattedResults);
    }
}
