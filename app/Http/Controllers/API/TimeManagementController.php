<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssignedTask;
use App\Models\EmployeeProductivityAnalytics;
use App\Models\PersonalTask;
use App\Models\ProjectEmployeeAssignment;
use App\Models\ProjectTask; // Import the model
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TimeManagementController extends Controller
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

    /* ─────────────────────  Employee Dashboard  ───────────────────── */

    /**
     * Get employee's main dashboard with all task categories
     */
    public function getEmployeeDashboard(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            if (! $employee) {
                return $this->fail('Unauthorized access', 401);
            }

            // Get personal tasks
            $personalTasks = PersonalTask::where('employee_id', $employee->id)
                ->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            // Get project tasks
            $projectTasks = ProjectTask::where('employee_id', $employee->id)
                ->with(['projectAssignment.project'])
                ->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            // Get assigned tasks
            $assignedTasks = AssignedTask::where('assigned_to', $employee->id)
                ->with(['task', 'assignedBy'])
                ->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            // Get important tasks from all categories
            $importantTasks = [
                'personal' => $personalTasks->where('is_important', true)->values(),
                'project' => $projectTasks->where('is_important', true)->values(),
                'assigned' => $assignedTasks->where('is_important', true)->values(),
            ];

            // Get recent analytics
            $analytics = EmployeeProductivityAnalytics::where('employee_id', $employee->id)
                ->orderBy('date', 'desc')
                ->take(7)
                ->get();

            $dashboard = [
                'personal_tasks' => $personalTasks,
                'project_tasks' => $projectTasks,
                'assigned_tasks' => $assignedTasks,
                'important_tasks' => $importantTasks,
                'analytics' => [
                    'current_streak' => $analytics->first()->streak_days ?? 0,
                    'max_streak' => $analytics->max('max_streak') ?? 0,
                    'weekly_data' => $analytics->reverse()->values(),
                ],
                'summary' => [
                    'total_tasks' => $personalTasks->count() + $projectTasks->count() + $assignedTasks->count(),
                    'completed_today' => $this->getCompletedTasksToday($employee->id),
                    'overdue_tasks' => $this->getOverdueTasks($employee->id),
                    'due_this_week' => $this->getDueThisWeek($employee->id),
                ],
            ];

            return $this->ok('Employee dashboard retrieved successfully', $dashboard);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving dashboard: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Personal Tasks  ───────────────────── */

    /**
     * Get all personal tasks for authenticated employee
     */
    public function getPersonalTasks(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            $query = PersonalTask::where('employee_id', $employee->id);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('important') && $request->important == 'true') {
                $query->where('is_important', true);
            }

            if ($request->has('pinned') && $request->pinned == 'true') {
                $query->where('is_pinned', true);
            }

            $tasks = $query->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            return $this->ok('Personal tasks retrieved successfully', $tasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving personal tasks: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create a new personal task
     */
    public function createPersonalTask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = PersonalTask::create([
                'employee_id' => $employee->id,
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'estimated_hours' => $request->estimated_hours,
                'notes' => $request->notes,
            ]);

            // Log activity
            $this->logTaskActivity('personal', $task->id, $employee->id, 'created');

            return $this->ok('Personal task created successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error creating personal task: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update a personal task
     */
    public function updatePersonalTask(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:to-do,doing,done,blocked',
            'progress_points' => 'sometimes|integer|min:0|max:100',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'actual_hours' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_important' => 'sometimes|boolean',
            'is_pinned' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = PersonalTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Task not found', 404);
            }

            $oldStatus = $task->status;
            $task->update($request->only([
                'title', 'description', 'status', 'progress_points',
                'due_date', 'estimated_hours', 'actual_hours', 'notes',
                'is_important', 'is_pinned',
            ]));

            // Log status change if status changed
            if ($request->has('status') && $oldStatus !== $request->status) {
                $this->logTaskActivity('personal', $task->id, $employee->id, 'status_changed', 'status', $oldStatus, $request->status);
            }

            // Update analytics if task completed
            if ($request->status === 'done' && $oldStatus !== 'done') {
                $this->updateEmployeeAnalytics($employee->id, 'task_completed');
            }

            return $this->ok('Personal task updated successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error updating personal task: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Dashboard Methods (Route Name Fixes)  ───────────────────── */

    /**
     * Alias for getEmployeeDashboard to match route naming
     */
    public function getDashboard(Request $request): JsonResponse
    {
        return $this->getEmployeeDashboard($request);
    }

    /**
     * Get productivity analytics for authenticated employee
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            if (! $employee) {
                return $this->fail('Unauthorized access', 401);
            }

            $analytics = EmployeeProductivityAnalytics::where('employee_id', $employee->id)
                ->orderBy('date', 'desc')
                ->take(30) // Last 30 days
                ->get();

            $summary = [
                'current_streak' => $analytics->first()->streak_days ?? 0,
                'max_streak' => $analytics->max('max_streak') ?? 0,
                'total_tasks_completed' => $analytics->sum('tasks_completed'),
                'total_hours_logged' => $analytics->sum('hours_logged'),
                'average_daily_tasks' => round($analytics->avg('tasks_completed'), 2),
                'productivity_trend' => $this->calculateProductivityTrend($analytics),
                'daily_data' => $analytics->reverse()->values(),
            ];

            return $this->ok('Analytics retrieved successfully', $summary);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving analytics: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get important tasks from all categories
     */
    public function getImportantTasks(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            if (! $employee) {
                return $this->fail('Unauthorized access', 401);
            }

            $personalTasks = PersonalTask::where('employee_id', $employee->id)
                ->where('is_important', true)
                ->where('status', '!=', 'done')
                ->orderBy('due_date', 'asc')
                ->get();

            $projectTasks = ProjectTask::where('employee_id', $employee->id)
                ->where('is_important', true)
                ->where('status', '!=', 'done')
                ->with(['projectAssignment.project'])
                ->orderBy('due_date', 'asc')
                ->get();

            $assignedTasks = AssignedTask::where('assigned_to', $employee->id)
                ->where('is_important', true)
                ->where('status', '!=', 'done')
                ->with(['task', 'assignedBy'])
                ->orderBy('due_date', 'asc')
                ->get();

            $importantTasks = [
                'personal' => $personalTasks,
                'project' => $projectTasks,
                'assigned' => $assignedTasks,
                'total_count' => $personalTasks->count() + $projectTasks->count() + $assignedTasks->count(),
            ];

            return $this->ok('Important tasks retrieved successfully', $importantTasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving important tasks: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Personal Tasks (Additional Methods)  ───────────────────── */

    /**
     * Alias for createPersonalTask to match route naming
     */
    public function storePersonalTask(Request $request): JsonResponse
    {
        return $this->createPersonalTask($request);
    }

    /**
     * Delete a personal task
     */
    public function deletePersonalTask(Request $request, int $id): JsonResponse
    {
        try {
            $employee = Auth::user();

            $task = PersonalTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Personal task not found or unauthorized', 404);
            }

            $task->delete();

            // Log activity
            $this->logTaskActivity('personal', $id, $employee->id, 'deleted');

            return $this->ok('Personal task deleted successfully');

        } catch (Throwable $e) {
            return $this->fail('Error deleting personal task: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update personal task status only
     */
    public function updatePersonalTaskStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:to-do,doing,done,blocked',
            'progress_points' => 'nullable|integer|min:0|max:100',
            'is_important' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = PersonalTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Personal task not found or unauthorized', 404);
            }

            $oldStatus = $task->status;
            $task->status = $request->status;

            if ($request->has('progress_points')) {
                $task->progress_points = $request->progress_points;
            }

            if ($request->has('is_important')) {
                $task->is_important = $request->is_important;
            }

            if ($request->has('is_pinned')) {
                $task->is_pinned = $request->is_pinned;
            }

            $task->save();

            // Log activity and update analytics
            $this->logTaskActivity('personal', $id, $employee->id, 'status_changed', 'status', $oldStatus, $request->status);

            if ($request->status === 'done' && $oldStatus !== 'done') {
                $this->updateAnalytics($employee->id, 'task_completed');
            }

            return $this->ok('Personal task status updated successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error updating personal task status: '.$e->getMessage(), 500);
        }
    }

    public function GetProject_Assignment(): JsonResponse
    {
        try {
            $employee = Auth::user();
            if (! $employee) {
                return $this->fail('Unauthorized access', 401);
            }

            $projectAssignments = ProjectEmployeeAssignment::where('employee_id', $employee->id)
                ->where('department_approval_status', 'approved') // Only get approved assignments
                ->with(['project:id,project_name,start_date,end_date'])
                ->get();

            $assignments = [];
            foreach ($projectAssignments as $assignment) {
                if ($assignment->project) {
                    $assignments[] = [
                        'assignment_id' => $assignment->id, // This is what should be used as project_assignment_id
                        'project_id' => $assignment->project->id,
                        'project_name' => $assignment->project->project_name,
                        'start_date' => $assignment->project->start_date,
                        'end_date' => $assignment->project->end_date,
                        'assigned_at' => $assignment->requested_at,
                        'approved_at' => $assignment->response_at,
                    ];
                }
            }

            return $this->ok('Project assignments retrieved successfully', $assignments);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving project assignments: '.$e->getMessage(), 500);
        }
    }
    /* ─────────────────────  Project Tasks  ───────────────────── */

    /**
     * Get project tasks for authenticated employee
     */
    public function getProjectTasks(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            $query = ProjectTask::where('employee_id', $employee->id)
                ->with(['projectAssignment.project']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('project_id')) {
                $query->whereHas('projectAssignment', function ($q) use ($request) {
                    $q->where('project_id', $request->project_id);
                });
            }

            $tasks = $query->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            return $this->ok('Project tasks retrieved successfully', $tasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving project tasks: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create a new project task
     */
    public function storeProjectTask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_assignment_id' => 'required|integer|exists:xxx_project_employee_assignments,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:to-do,doing,done,blocked',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_important' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            // Use database transaction with locking to prevent race conditions
            $task = DB::transaction(function () use ($request, $employee) {
                // Verify the project assignment belongs to this employee with locking
                $assignment = ProjectEmployeeAssignment::where('id', $request->project_assignment_id)
                    ->where('employee_id', $employee->id)
                    ->where('department_approval_status', 'approved')
                    ->lockForUpdate()
                    ->first();

                if (! $assignment) {
                    throw new \Exception('Invalid or unauthorized project assignment');
                }

                // Check for existing task with same title and assignment to prevent duplicates
                $existingTask = ProjectTask::where('employee_id', $employee->id)
                    ->where('project_assignment_id', $request->project_assignment_id)
                    ->where('title', $request->title)
                    ->where('auto_generated', false)
                    ->lockForUpdate()
                    ->first();

                return ProjectTask::create([
                    'employee_id' => $employee->id,
                    'project_assignment_id' => $request->project_assignment_id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'status' => $request->status ?? 'to-do',
                    'due_date' => $request->due_date,
                    'estimated_hours' => $request->estimated_hours,
                    'notes' => $request->notes,
                    'is_important' => $request->is_important ?? false,
                    'is_pinned' => $request->is_pinned ?? false,
                    'progress_points' => 0,
                    'auto_generated' => false,
                ]);
            });

            // Log activity
            $this->logTaskActivity('project', $task->id, $employee->id, 'created');

            // Update analytics
            $this->updateEmployeeAnalytics($employee->id, 'task_created');

            return $this->ok('Project task created successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error creating project task: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update a project task
     */
    public function updateProjectTask(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:to-do,doing,done,blocked',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'actual_hours' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_important' => 'sometimes|boolean',
            'is_pinned' => 'sometimes|boolean',
            'progress_points' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = ProjectTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Project task not found or unauthorized', 404);
            }

            $oldStatus = $task->status;

            // Update only the fields that were provided
            $task->update($request->only([
                'title', 'description', 'status', 'due_date',
                'estimated_hours', 'actual_hours', 'notes',
                'is_important', 'is_pinned', 'progress_points',
            ]));

            // Log status change if status changed
            if ($request->has('status') && $oldStatus !== $request->status) {
                $this->logTaskActivity('project', $id, $employee->id, 'status_changed', 'status', $oldStatus, $request->status);
            }

            // Log general update activity
            $this->logTaskActivity('project', $id, $employee->id, 'updated');

            // Update analytics if task completed
            if ($request->status === 'done' && $oldStatus !== 'done') {
                $this->updateEmployeeAnalytics($employee->id, 'task_completed');
            }

            return $this->ok('Project task updated successfully', $task->fresh());

        } catch (Throwable $e) {
            return $this->fail('Error updating project task: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update project task status
     */
    public function updateProjectTaskStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:to-do,doing,done,blocked',
            'progress_points' => 'nullable|integer|min:0|max:100',
            'is_important' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = ProjectTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Project task not found or unauthorized', 404);
            }

            $oldStatus = $task->status;
            $task->status = $request->status;

            if ($request->has('progress_points')) {
                $task->progress_points = $request->progress_points;
            }

            if ($request->has('is_important')) {
                $task->is_important = $request->is_important;
            }

            if ($request->has('is_pinned')) {
                $task->is_pinned = $request->is_pinned;
            }

            // Add support for additional fields
            if ($request->has('title')) {
                $task->title = $request->title;
            }

            if ($request->has('description')) {
                $task->description = $request->description;
            }

            if ($request->has('due_date')) {
                $task->due_date = $request->due_date;
            }

            if ($request->has('estimated_hours')) {
                $task->estimated_hours = $request->estimated_hours;
            }

            if ($request->has('notes')) {
                $task->notes = $request->notes;
            }

            $task->save();

            // Log activity
            $this->logTaskActivity('project', $id, $employee->id, 'status_changed', 'status', $oldStatus, $request->status);

            if ($request->status === 'done' && $oldStatus !== 'done') {
                $this->updateAnalytics($employee->id, 'task_completed');
            }

            return $this->ok('Project task status updated successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error updating project task status: '.$e->getMessage(), 500);
        }
    }

    /**
     * Log time spent on project task
     */
    public function logTimeSpent(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'required|numeric|min:0.1|max:24',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = ProjectTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Project task not found or unauthorized', 404);
            }

            // Update actual hours
            $task->actual_hours = ($task->actual_hours ?? 0) + $request->hours;
            $task->save();

            // Update analytics
            $this->updateAnalytics($employee->id, 'hours_logged', $request->hours);

            // Log activity
            $this->logTaskActivity('project', $id, $employee->id, 'time_logged', 'actual_hours', null, $request->hours);

            return $this->ok('Time logged successfully', [
                'task_id' => $id,
                'hours_logged' => $request->hours,
                'total_actual_hours' => $task->actual_hours,
                'date' => $request->date ?? now()->toDateString(),
            ]);

        } catch (Throwable $e) {
            return $this->fail('Error logging time: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete a project task
     */
    public function deleteProjectTask(Request $request, int $id): JsonResponse
    {
        try {
            $employee = Auth::user();

            // First check if task exists at all
            $taskExists = ProjectTask::find($id);
            if (! $taskExists) {
                return $this->fail('Project task with ID '.$id.' does not exist', 404);
            }

            // Check if task belongs to the authenticated user
            $task = ProjectTask::where('employee_id', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Project task not found or you are not authorized to delete this task (belongs to employee ID: '.$taskExists->employee_id.', your ID: '.$employee->id.')', 403);
            }

            // Log activity before deleting
            $this->logTaskActivity('project', $id, $employee->id, 'deleted');
            
            // Delete the task
            $task->delete();

            return $this->ok('Project task deleted successfully');

        } catch (Throwable $e) {
            return $this->fail('Error deleting project task: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Assigned Tasks  ───────────────────── */

    /**
     * Get assigned tasks for authenticated employee
     */
    public function getAssignedTasks(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user();
            $query = AssignedTask::where('assigned_to', $employee->id)
                ->with(['task', 'assignedBy']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('assigned_by')) {
                $query->where('assigned_by', $request->assigned_by);
            }

            $tasks = $query->orderBy('is_pinned', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();

            return $this->ok('Assigned tasks retrieved successfully', $tasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving assigned tasks: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update assigned task status
     */
    public function updateAssignedTaskStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:to-do,doing,done,blocked',
            'progress_points' => 'nullable|integer|min:0|max:100',
            'is_important' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = AssignedTask::where('assigned_to', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Assigned task not found or unauthorized', 404);
            }

            // Check permissions
            if ($task->permission_level === 'view_only') {
                return $this->fail('You do not have permission to edit this task', 403);
            }

            $oldStatus = $task->status;
            $task->status = $request->status;

            if ($request->has('progress_points') && $task->permission_level !== 'view_only') {
                $task->progress_points = $request->progress_points;
            }

            if ($request->has('is_important')) {
                $task->is_important = $request->is_important;
            }

            if ($request->has('is_pinned')) {
                $task->is_pinned = $request->is_pinned;
            }

            $task->save();

            // Log activity
            $this->logTaskActivity('assigned', $id, $employee->id, 'status_changed', 'status', $oldStatus, $request->status);

            if ($request->status === 'done' && $oldStatus !== 'done') {
                $this->updateAnalytics($employee->id, 'task_completed');
            }

            return $this->ok('Assigned task status updated successfully', $task);

        } catch (Throwable $e) {
            return $this->fail('Error updating assigned task status: '.$e->getMessage(), 500);
        }
    }

    /**
     * Submit feedback for assigned task
     */
    public function submitTaskFeedback(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'feedback' => 'required|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = AssignedTask::where('assigned_to', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Assigned task not found or unauthorized', 404);
            }

            // Update task with feedback
            $task->completion_feedback = $request->feedback;
            if ($request->has('rating')) {
                $task->completion_rating = $request->rating;
            }
            $task->feedback_submitted_at = now();
            $task->save();

            // Log activity
            $this->logTaskActivity('assigned', $id, $employee->id, 'feedback_submitted');

            return $this->ok('Task feedback submitted successfully', [
                'task_id' => $id,
                'feedback' => $request->feedback,
                'rating' => $request->rating,
                'submitted_at' => $task->feedback_submitted_at,
            ]);

        } catch (Throwable $e) {
            return $this->fail('Error submitting task feedback: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update assigned task flags (pin/important)
     */
    public function updateAssignedTask(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_pinned' => 'sometimes|boolean',
            'is_important' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
            'progress_points' => 'sometimes|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = AssignedTask::where('assigned_to', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Assigned task not found or unauthorized', 404);
            }

            // Check permissions
            if ($task->permission_level === 'view_only') {
                return $this->fail('You do not have permission to edit this task', 403);
            }

            // Update only the fields that were provided
            $task->update($request->only([
                'is_pinned', 'is_important', 'notes', 'progress_points',
            ]));

            // Log activity
            $this->logTaskActivity('assigned', $id, $employee->id, 'updated');

            return $this->ok('Assigned task updated successfully', $task->fresh());

        } catch (Throwable $e) {
            return $this->fail('Error updating assigned task: '.$e->getMessage(), 500);
        }
    }

    /**
     * Log time spent on assigned task
     */
    public function logAssignedTaskTimeSpent(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'required|numeric|min:0.1|max:24',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $employee = Auth::user();

            $task = AssignedTask::where('assigned_to', $employee->id)->find($id);
            if (! $task) {
                return $this->fail('Assigned task not found or unauthorized', 404);
            }

            // Check permissions
            if ($task->permission_level === 'view_only') {
                return $this->fail('You do not have permission to log time for this task', 403);
            }

            // Update actual hours
            $task->actual_hours = ($task->actual_hours ?? 0) + $request->hours;
            $task->save();

            // Update analytics
            $this->updateAnalytics($employee->id, 'hours_logged', $request->hours);

            // Log activity
            $this->logTaskActivity('assigned', $id, $employee->id, 'time_logged', 'actual_hours', null, $request->hours);

            return $this->ok('Time logged successfully', [
                'task_id' => $id,
                'hours_logged' => $request->hours,
                'total_actual_hours' => $task->actual_hours,
                'date' => $request->date ?? now()->toDateString(),
            ]);

        } catch (Throwable $e) {
            return $this->fail('Error logging time: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Helper Methods  ───────────────────── */

    private function calculateProductivityTrend($analytics): string
    {
        if ($analytics->count() < 7) {
            return 'insufficient_data';
        }

        $recent = $analytics->take(7)->avg('tasks_completed');
        $previous = $analytics->skip(7)->take(7)->avg('tasks_completed');

        if ($recent > $previous * 1.1) {
            return 'increasing';
        } elseif ($recent < $previous * 0.9) {
            return 'decreasing';
        }

        return 'stable';
    }

    private function updateAnalytics(int $employeeId, string $action, ?float $hours = null): void
    {
        $today = now()->toDateString();

        try {
            $analytics = EmployeeProductivityAnalytics::firstOrCreate(
                ['employee_id' => $employeeId, 'date' => $today],
                ['tasks_completed' => 0, 'tasks_created' => 0, 'total_progress_points' => 0, 'hours_logged' => 0]
            );

            if ($action === 'task_completed') {
                $analytics->increment('tasks_completed');
                $this->updateStreak($employeeId);
            } elseif ($action === 'task_created') {
                $analytics->increment('tasks_created');
            } elseif ($action === 'hours_logged' && $hours) {
                $analytics->hours_logged += $hours;
                $analytics->save();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle race condition - try to find and update existing record
            if ($e->getCode() == 23000) { // UNIQUE constraint violation
                $analytics = EmployeeProductivityAnalytics::where('employee_id', $employeeId)
                    ->where('date', $today)
                    ->first();
                
                if ($analytics) {
                    if ($action === 'task_completed') {
                        $analytics->increment('tasks_completed');
                        $this->updateStreak($employeeId);
                    } elseif ($action === 'task_created') {
                        $analytics->increment('tasks_created');
                    } elseif ($action === 'hours_logged' && $hours) {
                        $analytics->hours_logged += $hours;
                        $analytics->save();
                    }
                }
            } else {
                throw $e; // Re-throw if it's a different error
            }
        }
    }

    private function getCompletedTasksToday(int $employeeId): int
    {
        $today = now()->toDateString();

        return PersonalTask::where('employee_id', $employeeId)
            ->where('status', 'done')
            ->whereDate('updated_at', $today)
            ->count() +
        ProjectTask::where('employee_id', $employeeId)
            ->where('status', 'done')
            ->whereDate('updated_at', $today)
            ->count() +
        AssignedTask::where('assigned_to', $employeeId)
            ->where('status', 'done')
            ->whereDate('updated_at', $today)
            ->count();
    }

    private function getOverdueTasks(int $employeeId): int
    {
        $today = now()->toDateString();

        return PersonalTask::where('employee_id', $employeeId)
            ->where('status', '!=', 'done')
            ->where('due_date', '<', $today)
            ->count() +
        ProjectTask::where('employee_id', $employeeId)
            ->where('status', '!=', 'done')
            ->where('due_date', '<', $today)
            ->count() +
        AssignedTask::where('assigned_to', $employeeId)
            ->where('status', '!=', 'done')
            ->where('due_date', '<', $today)
            ->count();
    }

    private function getDueThisWeek(int $employeeId): int
    {
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        return PersonalTask::where('employee_id', $employeeId)
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
            ->count() +
        ProjectTask::where('employee_id', $employeeId)
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
            ->count() +
        AssignedTask::where('assigned_to', $employeeId)
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
            ->count();
    }

    private function logTaskActivity(string $taskType, int $taskId, int $employeeId, string $action, ?string $field = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        \App\Models\TaskActivityLog::create([
            'task_type' => $taskType,
            'task_id' => $taskId,
            'employee_id' => $employeeId,
            'action' => $action,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    private function updateEmployeeAnalytics(int $employeeId, string $action): void
    {
        $today = now()->toDateString();

        try {
            $analytics = EmployeeProductivityAnalytics::firstOrCreate(
                ['employee_id' => $employeeId, 'date' => $today],
                ['tasks_completed' => 0, 'tasks_created' => 0, 'total_progress_points' => 0, 'hours_logged' => 0]
            );

            if ($action === 'task_completed') {
                $analytics->increment('tasks_completed');
                $this->updateStreak($employeeId);
            } elseif ($action === 'task_created') {
                $analytics->increment('tasks_created');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle race condition - try to find and update existing record
            if ($e->getCode() == 23000) { // UNIQUE constraint violation
                $analytics = EmployeeProductivityAnalytics::where('employee_id', $employeeId)
                    ->where('date', $today)
                    ->first();
                
                if ($analytics) {
                    if ($action === 'task_completed') {
                        $analytics->increment('tasks_completed');
                        $this->updateStreak($employeeId);
                    } elseif ($action === 'task_created') {
                        $analytics->increment('tasks_created');
                    }
                }
            } else {
                throw $e; // Re-throw if it's a different error
            }
        }
    }

    private function updateStreak(int $employeeId): void
    {
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        $todayAnalytics = EmployeeProductivityAnalytics::where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        $yesterdayAnalytics = EmployeeProductivityAnalytics::where('employee_id', $employeeId)
            ->where('date', $yesterday)
            ->first();

        if ($todayAnalytics) {
            if ($yesterdayAnalytics && $yesterdayAnalytics->tasks_completed > 0) {
                // Continue streak
                $todayAnalytics->streak_days = ($yesterdayAnalytics->streak_days ?? 0) + 1;
            } else {
                // Start new streak
                $todayAnalytics->streak_days = 1;
            }

            // Update max streak if current streak is higher
            $todayAnalytics->max_streak = max($todayAnalytics->max_streak ?? 0, $todayAnalytics->streak_days);
            $todayAnalytics->save();
        }
    }
}
