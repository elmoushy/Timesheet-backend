<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class TaskController extends Controller
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
    private function taskRules(int $id = 0): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $id > 0 ? Rule::unique('xxx_tasks', 'name')->ignore($id) : Rule::unique('xxx_tasks', 'name'),
            ],
            'task_type' => 'required|string|max:100',
            'department_id' => 'required|integer|exists:xxx_departments,id',
            'project_id' => 'nullable|integer|exists:xxx_projects,id',
            'description' => 'nullable|string',
        ];
    }

    /* ─────────────────────  Index & List  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        // Load only the minimal required relationships
        $query = Task::with(['department:id,name', 'project:id,project_name,description,department_id,client_id,status,start_date,end_date']);

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('task_type', 'LIKE', $searchTerm)
                    ->orWhere('description', 'LIKE', $searchTerm);
            });
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Apply project filter if provided
        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        // Apply task type filter if provided
        if ($request->has('task_type')) {
            $query->where('task_type', $request->input('task_type'));
        }

        $tasks = $query->paginate($request->input('per_page', 10));

        // Transform using TaskResource collection
        $transformedData = $tasks->toArray();
        $transformedData['data'] = TaskResource::collection($tasks->items())->resolve();

        return $this->ok('Tasks fetched successfully', $transformedData);
    }

    public function list(): JsonResponse
    {
        // Return all tasks with minimal relationships for dropdown/select lists
        $tasks = Task::with(['department:id,name', 'project:id,project_name,description,department_id,client_id,status,start_date,end_date'])
            ->orderBy('name')
            ->get();

        return $this->ok('All tasks fetched successfully', TaskResource::collection($tasks));
    }

    /* ─────────────────────  Show  ───────────────────── */
    public function show(int $id): JsonResponse
    {
        $task = Task::with(['department', 'project'])->find($id);

        return $task
            ? $this->ok('Task fetched successfully', $task)
            : $this->fail('Task not found', 404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->taskRules());
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            $task = Task::create($request->only([
                'name', 'task_type', 'department_id', 'project_id', 'description',
            ]));

            return $this->ok('Task created successfully', $task->load(['department', 'project']), 201);
        } catch (Throwable $e) {
            return $this->fail('Error creating task: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) {
            return $this->fail('Task not found', 404);
        }

        $v = Validator::make($request->all(), $this->taskRules($id));
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            $task->update($request->only([
                'name', 'task_type', 'department_id', 'project_id', 'description',
            ]));

            return $this->ok('Task updated successfully', $task->load(['department', 'project']));
        } catch (Throwable $e) {
            return $this->fail('Error updating task: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) {
            return $this->fail('Task not found', 404);
        }

        try {
            $task->delete();

            return $this->ok('Task deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting task: '.$e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non-empty array', 422);
        }

        try {
            $deleted = Task::whereIn('id', $ids)->delete();

            return $this->ok($deleted
                ? "$deleted task(s) deleted successfully"
                : 'No tasks were deleted'
            );
        } catch (Throwable $e) {
            return $this->fail('Error deleting tasks: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function search(Request $request): JsonResponse
    {
        $query = Task::query()
            ->select('id', 'name', 'task_type', 'project_id');

        // Apply filters if provided
        if ($request->has('term')) {
            $searchTerm = '%'.$request->input('term').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('task_type', 'LIKE', $searchTerm);
            });
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Apply project filter if provided
        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        // Apply task type filter if provided
        if ($request->has('task_type')) {
            $query->where('task_type', $request->input('task_type'));
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->limit(50)->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'task_type' => $task->task_type,
                'project_id' => $task->project_id,
                'display_text' => "{$task->name} ({$task->task_type})",
            ];
        });

        return $this->ok('Task search results', $formattedResults);
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
            $searchTerm = '%'.$request->input('term').'%';
            $query->where('name', 'LIKE', $searchTerm);
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'display_text' => $department->name,
            ];
        });

        return $this->ok('Department search results', $formattedResults);
    }

    /**
     * Get tasks by project
     */
    public function getTasksByProject(int $projectId): JsonResponse
    {
        try {
            $project = Project::find($projectId);

            if (! $project) {
                return $this->fail('Project not found', 404);
            }

            $tasks = Task::where('project_id', $projectId)
                ->orderBy('name')
                ->get();

            return $this->ok('Project tasks retrieved successfully', $tasks);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving project tasks: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get projects by department
     */
    public function getProjectsByDepartment(int $departmentId): JsonResponse
    {
        try {
            $department = Department::find($departmentId);

            if (! $department) {
                return $this->fail('Department not found', 404);
            }

            $projects = Project::where('department_id', $departmentId)
                ->orderBy('id')
                ->get();

            return $this->ok('Department projects retrieved successfully', $projects);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving department projects: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get projects list for dropdown (filtered by department if provided)
     */
    public function projectList(Request $request): JsonResponse
    {
        try {
            $query = Project::query()
                ->select('id', 'project_name', 'description', 'department_id', 'client_id', 'status', 'start_date', 'end_date')
                ->with('client:id,name');

            // Apply department filter if provided
            if ($request->has('department_id')) {
                $query->where('department_id', $request->input('department_id'));
            }

            // Apply search term if provided
            if ($request->has('term')) {
                $searchTerm = '%'.$request->input('term').'%';
                $query->where('project_name', 'LIKE', $searchTerm);
            }

            $projects = $query->orderBy('project_name')->get();

            // Format results for dropdown
            $formattedResults = $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'project_name' => $project->project_name,
                    'description' => $project->description,
                    'client_name' => $project->client ? $project->client->name : '',
                    'display_text' => $project->project_name . ($project->client ? ' (' . $project->client->name . ')' : ''),
                ];
            });

            return $this->ok('Project list retrieved successfully', $formattedResults);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving project list: '.$e->getMessage(), 500);
        }
    }
}
