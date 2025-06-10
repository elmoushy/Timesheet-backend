<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssignedTask;
use App\Models\Task;
use App\Models\Employee;
use App\Models\EmployeeWorkloadCapacity;
use App\Models\BulkTaskOperation;
use App\Models\DepartmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DepartmentManagerController extends Controller
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
     * Check if the authenticated user is a department manager
     */
    private function isDepartmentManager(): bool
    {
        $user = Auth::user();
        return DepartmentManager::where('employee_id', $user->id)->exists();
    }

    /**
     * Get departments managed by the authenticated user
     */
    private function getManagedDepartmentIds(): array
    {
        $user = Auth::user();
        return DepartmentManager::where('employee_id', $user->id)
            ->pluck('department_id')
            ->toArray();
    }

    /* ─────────────────────  Department Manager Dashboard  ───────────────────── */

    /**
     * Get department manager dashboard with overview
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            // Get employees in managed departments
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)
                ->with(['department'])
                ->get();

            // Get task statistics
            $taskStats = $this->getTaskStatistics($managedDepartmentIds);

            // Get workload heatmap data
            $workloadData = $this->generateWorkloadHeatmapData($employees->pluck('id')->toArray());

            // Get recent assigned tasks
            $recentTasks = AssignedTask::whereIn('assigned_to', $employees->pluck('id'))
                ->with(['masterTask', 'assignedEmployee', 'assignedByManager'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function($task) {
                    return [
                        'id' => $task->id,
                        'task_id' => $task->task_id,
                        'assigned_to' => $task->assigned_to,
                        'assigned_by' => $task->assigned_by,
                        'status' => $task->status,
                        'due_date' => $task->due_date,
                        'created_at' => $task->created_at,
                        'task' => [
                            'id' => $task->masterTask->id ?? null,
                            'title' => $task->masterTask->title ?? 'Unknown Task',
                            'description' => $task->masterTask->description ?? '',
                        ],
                        'assigned_to_employee' => [
                            'id' => $task->assignedEmployee->id ?? null,
                            'first_name' => $task->assignedEmployee->first_name ?? '',
                            'last_name' => $task->assignedEmployee->last_name ?? '',
                        ],
                        'assigned_by_employee' => [
                            'id' => $task->assignedByManager->id ?? null,
                            'first_name' => $task->assignedByManager->first_name ?? '',
                            'last_name' => $task->assignedByManager->last_name ?? '',
                        ],
                    ];
                });

            $dashboard = [
                'managed_departments' => $managedDepartmentIds,
                'employees_count' => $employees->count(),
                'task_statistics' => $taskStats,
                'workload_heatmap' => $workloadData,
                'recent_tasks' => $recentTasks,
                'important_tasks_count' => AssignedTask::whereIn('assigned_to', $employees->pluck('id'))
                    ->where('is_important', true)
                    ->where('status', '!=', 'done')
                    ->count(),
            ];

            return $this->ok('Department manager dashboard retrieved successfully', $dashboard);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving dashboard: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Task Assignment  ───────────────────── */

    /**
     * Get available tasks for assignment
     */
    public function getAvailableTasks(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $tasks = Task::whereIn('department_id', $managedDepartmentIds)
                ->with(['department'])
                ->get();

            return $this->ok('Available tasks retrieved successfully', $tasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving available tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get employees in managed departments
     */
    public function getManagedEmployees(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $employees = Employee::whereIn('department_id', $managedDepartmentIds)
                ->with(['department'])
                ->where('user_status', 'active')
                ->get();

            // Add workload information
            $employeesWithWorkload = $employees->map(function ($employee) {
                $weekStart = now()->startOfWeek()->toDateString();
                $workload = EmployeeWorkloadCapacity::where('employee_id', $employee->id)
                    ->where('week_start_date', $weekStart)
                    ->first();

                return [
                    'id' => $employee->id,
                    'name' => $employee->getFullNameAttribute(),
                    'email' => $employee->work_email,
                    'department' => $employee->department->name ?? 'Unknown',
                    'workload' => [
                        'capacity_hours' => $workload->weekly_capacity_hours ?? 40,
                        'planned_hours' => $workload->current_planned_hours ?? 0,
                        'percentage' => $workload->workload_percentage ?? 0,
                        'status' => $workload->workload_status ?? 'optimal',
                    ]
                ];
            });

            return $this->ok('Managed employees retrieved successfully', $employeesWithWorkload);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving managed employees: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign task to employee
     */
    public function assignTask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer|exists:xxx_tasks,id',
            'employee_id' => 'required|integer|exists:xxx_employees,id',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'permission_level' => 'required|in:view_only,edit_progress,full_edit',
            'assignment_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $manager = Auth::user();
            $managedDepartmentIds = $this->getManagedDepartmentIds();

            // Verify task belongs to managed department
            $task = Task::whereIn('department_id', $managedDepartmentIds)->find($request->task_id);
            if (!$task) {
                return $this->fail('Task not found in your managed departments', 404);
            }

            // Verify employee belongs to managed department
            $employee = Employee::whereIn('department_id', $managedDepartmentIds)->find($request->employee_id);
            if (!$employee) {
                return $this->fail('Employee not found in your managed departments', 404);
            }

            // Check if task is already assigned to this employee
            $existingAssignment = AssignedTask::where('task_id', $request->task_id)
                ->where('assigned_to', $request->employee_id)
                ->first();

            if ($existingAssignment) {
                return $this->fail('Task is already assigned to this employee', 422);
            }

            DB::beginTransaction();

            // Create task assignment
            $assignedTask = AssignedTask::create([
                'task_id' => $request->task_id,
                'assigned_to' => $request->employee_id,
                'assigned_by' => $manager->id,
                'due_date' => $request->due_date,
                'estimated_hours' => $request->estimated_hours,
                'permission_level' => $request->permission_level,
                'assignment_notes' => $request->assignment_notes,
            ]);

            // Update employee workload if estimated hours provided
            if ($request->estimated_hours) {
                $this->updateEmployeeWorkload($request->employee_id, $request->estimated_hours);
            }

            // Log activity
            $this->logTaskActivity('assigned', $assignedTask->id, $manager->id, 'created');

            DB::commit();

            $assignedTask->load(['masterTask', 'assignedEmployee', 'assignedByManager']);

            return $this->ok('Task assigned successfully', $assignedTask);

        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error assigning task: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Workload Management  ───────────────────── */

    /**
     * Get workload heatmap for all managed employees
     */
    public function getWorkloadHeatmap(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->get();

            $workloadData = $this->generateWorkloadHeatmapData($employees->pluck('id')->toArray());

            return $this->ok('Workload heatmap retrieved successfully', $workloadData);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving workload heatmap: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Bulk Operations  ───────────────────── */

    /**
     * Initiate bulk task operation
     */
    public function bulkTaskOperation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operation_type' => 'required|in:reassign,update_status,update_due_date,update_priority,bulk_delete',
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:assigned_tasks,id',
            'operation_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $manager = Auth::user();
            $managedDepartmentIds = $this->getManagedDepartmentIds();

            // Verify all tasks belong to managed employees
            $tasks = AssignedTask::whereIn('id', $request->task_ids)
                ->whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                    $query->whereIn('department_id', $managedDepartmentIds);
                })
                ->get();

            if ($tasks->count() !== count($request->task_ids)) {
                return $this->fail('Some tasks do not belong to your managed employees', 403);
            }

            // Create bulk operation record
            $bulkOperation = BulkTaskOperation::create([
                'initiated_by' => $manager->id,
                'operation_type' => $request->operation_type,
                'task_ids' => $request->task_ids,
                'operation_data' => $request->operation_data,
                'total_tasks' => count($request->task_ids),
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            // Process the bulk operation
            $this->processBulkOperation($bulkOperation);

            return $this->ok('Bulk operation initiated successfully', $bulkOperation);

        } catch (Throwable $e) {
            return $this->fail('Error initiating bulk operation: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Helper Methods  ───────────────────── */

    private function getTaskStatistics(array $departmentIds): array
    {
        $employees = Employee::whereIn('department_id', $departmentIds)->pluck('id');

        return [
            'total_assigned' => AssignedTask::whereIn('assigned_to', $employees)->count(),
            'to_do' => AssignedTask::whereIn('assigned_to', $employees)->where('status', 'to-do')->count(),
            'doing' => AssignedTask::whereIn('assigned_to', $employees)->where('status', 'doing')->count(),
            'done' => AssignedTask::whereIn('assigned_to', $employees)->where('status', 'done')->count(),
            'blocked' => AssignedTask::whereIn('assigned_to', $employees)->where('status', 'blocked')->count(),
            'overdue' => AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', '!=', 'done')
                ->where('due_date', '<', now())
                ->count(),
        ];
    }

    private function generateWorkloadHeatmapData(array $employeeIds): array
    {
        $weekStart = now()->startOfWeek()->toDateString();

        return EmployeeWorkloadCapacity::whereIn('employee_id', $employeeIds)
            ->where('week_start_date', $weekStart)
            ->with('employee:id,first_name,last_name')
            ->get()
            ->map(function ($workload) {
                return [
                    'employee_id' => $workload->employee_id,
                    'employee_name' => $workload->employee->first_name . ' ' . $workload->employee->last_name,
                    'capacity_hours' => $workload->weekly_capacity_hours,
                    'planned_hours' => $workload->current_planned_hours,
                    'percentage' => $workload->workload_percentage,
                    'status' => $workload->workload_status,
                    'color' => $this->getWorkloadColor($workload->workload_status),
                ];
            })
            ->toArray();
    }

    private function getWorkloadColor(string $status): string
    {
        return match($status) {
            'under_utilized' => '#90EE90', // Light green
            'optimal' => '#32CD32',        // Green
            'over_loaded' => '#FFA500',    // Orange
            'critical' => '#FF6347',       // Red
            default => '#D3D3D3'           // Light gray
        };
    }

    private function updateEmployeeWorkload(int $employeeId, int $hours): void
    {
        $weekStart = now()->startOfWeek()->toDateString();

        $workload = EmployeeWorkloadCapacity::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'week_start_date' => $weekStart
            ],
            [
                'weekly_capacity_hours' => 40,
                'current_planned_hours' => 0,
            ]
        );

        $workload->current_planned_hours += $hours;
        $workload->workload_percentage = ($workload->current_planned_hours / $workload->weekly_capacity_hours) * 100;

        // Update status based on percentage
        if ($workload->workload_percentage < 70) {
            $workload->workload_status = 'under_utilized';
        } elseif ($workload->workload_percentage <= 100) {
            $workload->workload_status = 'optimal';
        } elseif ($workload->workload_percentage <= 120) {
            $workload->workload_status = 'over_loaded';
        } else {
            $workload->workload_status = 'critical';
        }

        $workload->save();
    }

    private function processBulkOperation(BulkTaskOperation $operation): void
    {
        try {
            $operation->update(['status' => 'in_progress', 'started_at' => now()]);

            $processedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($operation->task_ids as $taskId) {
                try {
                    $this->processSingleBulkTask($taskId, $operation->operation_type, $operation->operation_data);
                    $processedCount++;
                } catch (Throwable $e) {
                    $failedCount++;
                    $errors[] = "Task ID {$taskId}: " . $e->getMessage();
                }
            }

            $operation->update([
                'status' => $failedCount > 0 ? 'failed' : 'completed',
                'processed_tasks' => $processedCount,
                'failed_tasks' => $failedCount,
                'error_log' => $errors,
                'completed_at' => now(),
            ]);

        } catch (Throwable $e) {
            $operation->update([
                'status' => 'failed',
                'error_log' => [$e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }

    private function processSingleBulkTask(int $taskId, string $operationType, array $operationData): void
    {
        $task = AssignedTask::findOrFail($taskId);

        switch ($operationType) {
            case 'reassign':
                $task->update(['assigned_to' => $operationData['new_assignee_id']]);
                break;
            case 'update_status':
                $task->update(['status' => $operationData['new_status']]);
                break;
            case 'update_due_date':
                $task->update(['due_date' => $operationData['new_due_date']]);
                break;
            case 'update_priority':
                $task->update(['is_important' => $operationData['is_important']]);
                break;
            case 'bulk_delete':
                $task->delete();
                break;
        }
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

    /**
     * Get employees in managed departments (simple list for dropdowns)
     */
    public function getManagedEmployeesSimple(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $employees = Employee::whereIn('department_id', $managedDepartmentIds)
                ->where('user_status', 'active')
                ->select('id', 'first_name', 'middle_name', 'last_name', 'work_email', 'department_id')
                ->with('department:id,name')
                ->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->getFullNameAttribute(),
                        'email' => $employee->work_email,
                        'department_name' => $employee->department->name ?? 'Unknown'
                    ];
                });

            return $this->ok('Managed employees list retrieved successfully', $employees);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving managed employees list: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Additional Task Management Methods  ───────────────────── */

    /**
     * Get assigned tasks for managed employees
     */
    public function getAssignedTasks(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->pluck('id');

            $query = AssignedTask::whereIn('assigned_to', $employees)
                ->with(['masterTask', 'assignedEmployee', 'assignedByManager']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('employee_id')) {
                $query->where('assigned_to', $request->employee_id);
            }

            if ($request->has('overdue')) {
                $query->where('due_date', '<', now())->where('status', '!=', 'done');
            }

            $tasks = $query->orderBy('created_at', 'desc')->paginate(15);

            return $this->ok('Assigned tasks retrieved successfully', $tasks);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving assigned tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update task permissions
     */
    public function updateTaskPermissions(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_level' => 'required|in:view_only,edit_progress,full_edit',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $assignedTask = AssignedTask::whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                $query->whereIn('department_id', $managedDepartmentIds);
            })->findOrFail($id);

            $oldPermission = $assignedTask->permission_level;
            $assignedTask->update(['permission_level' => $request->permission_level]);

            $this->logTaskActivity('assigned', $assignedTask->id, Auth::user()->id, 'updated',
                'permission_level', $oldPermission, $request->permission_level);

            return $this->ok('Task permissions updated successfully', $assignedTask);

        } catch (Throwable $e) {
            return $this->fail('Error updating task permissions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:to-do,doing,done,blocked',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $assignedTask = AssignedTask::whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                $query->whereIn('department_id', $managedDepartmentIds);
            })->findOrFail($id);

            $oldStatus = $assignedTask->status;
            $assignedTask->update([
                'status' => $request->status,
                'status_notes' => $request->notes,
                'completed_at' => $request->status === 'done' ? now() : null,
            ]);

            $this->logTaskActivity('assigned', $assignedTask->id, Auth::user()->id, 'updated',
                'status', $oldStatus, $request->status);

            return $this->ok('Task status updated successfully', $assignedTask);

        } catch (Throwable $e) {
            return $this->fail('Error updating task status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete assigned task
     */
    public function deleteAssignedTask($id): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $assignedTask = AssignedTask::whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                $query->whereIn('department_id', $managedDepartmentIds);
            })->findOrFail($id);

            $this->logTaskActivity('assigned', $assignedTask->id, Auth::user()->id, 'deleted');

            $assignedTask->delete();

            return $this->ok('Assigned task deleted successfully');

        } catch (Throwable $e) {
            return $this->fail('Error deleting assigned task: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Additional Bulk Operations  ───────────────────── */

    /**
     * Bulk assign tasks to employees
     */
    public function bulkAssignTasks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:xxx_tasks,id',
            'employee_id' => 'required|integer|exists:xxx_employees,id',
            'due_date' => 'nullable|date',
            'permission_level' => 'required|in:view_only,edit_progress,full_edit',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $manager = Auth::user();

            // Verify tasks belong to managed departments
            $tasks = Task::whereIn('id', $request->task_ids)
                ->whereIn('department_id', $managedDepartmentIds)
                ->get();

            if ($tasks->count() !== count($request->task_ids)) {
                return $this->fail('Some tasks do not belong to your managed departments', 403);
            }

            // Verify employee belongs to managed department
            $employee = Employee::whereIn('department_id', $managedDepartmentIds)->find($request->employee_id);
            if (!$employee) {
                return $this->fail('Employee not found in your managed departments', 404);
            }

            $assignedTasks = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->task_ids as $taskId) {
                try {
                    // Check if already assigned
                    $existing = AssignedTask::where('task_id', $taskId)
                        ->where('assigned_to', $request->employee_id)
                        ->first();

                    if ($existing) {
                        $errors[] = "Task ID {$taskId} is already assigned to this employee";
                        continue;
                    }

                    $assignedTask = AssignedTask::create([
                        'task_id' => $taskId,
                        'assigned_to' => $request->employee_id,
                        'assigned_by' => $manager->id,
                        'due_date' => $request->due_date,
                        'permission_level' => $request->permission_level,
                        'assignment_notes' => $request->notes,
                    ]);

                    $assignedTasks[] = $assignedTask;
                    $this->logTaskActivity('assigned', $assignedTask->id, $manager->id, 'created');

                } catch (Throwable $e) {
                    $errors[] = "Task ID {$taskId}: " . $e->getMessage();
                }
            }

            DB::commit();

            $result = [
                'assigned_tasks' => $assignedTasks,
                'total_assigned' => count($assignedTasks),
                'errors' => $errors,
            ];

            return $this->ok('Bulk task assignment completed', $result);

        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error in bulk task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update task status
     */
    public function bulkUpdateTaskStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:assigned_tasks,id',
            'status' => 'required|in:to-do,doing,done,blocked',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $tasks = AssignedTask::whereIn('id', $request->task_ids)
                ->whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                    $query->whereIn('department_id', $managedDepartmentIds);
                })
                ->get();

            if ($tasks->count() !== count($request->task_ids)) {
                return $this->fail('Some tasks do not belong to your managed employees', 403);
            }

            $updatedTasks = [];
            $manager = Auth::user();

            DB::beginTransaction();

            foreach ($tasks as $task) {
                $oldStatus = $task->status;
                $task->update([
                    'status' => $request->status,
                    'status_notes' => $request->notes,
                    'completed_at' => $request->status === 'done' ? now() : null,
                ]);

                $this->logTaskActivity('assigned', $task->id, $manager->id, 'updated',
                    'status', $oldStatus, $request->status);

                $updatedTasks[] = $task;
            }

            DB::commit();

            return $this->ok('Bulk status update completed successfully', [
                'updated_tasks' => $updatedTasks->count(),
                'new_status' => $request->status,
            ]);

        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error in bulk status update: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update task deadlines
     */
    public function bulkUpdateDeadlines(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:assigned_tasks,id',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $tasks = AssignedTask::whereIn('id', $request->task_ids)
                ->whereHas('assignedEmployee', function($query) use ($managedDepartmentIds) {
                    $query->whereIn('department_id', $managedDepartmentIds);
                })
                ->get();

            if ($tasks->count() !== count($request->task_ids)) {
                return $this->fail('Some tasks do not belong to your managed employees', 403);
            }

            $updatedTasks = [];
            $manager = Auth::user();

            DB::beginTransaction();

            foreach ($tasks as $task) {
                $oldDueDate = $task->due_date;
                $task->update([
                    'due_date' => $request->due_date,
                    'deadline_notes' => $request->notes,
                ]);

                $this->logTaskActivity('assigned', $task->id, $manager->id, 'updated',
                    'due_date', $oldDueDate, $request->due_date);

                $updatedTasks[] = $task;
            }

            DB::commit();

            return $this->ok('Bulk deadline update completed successfully', [
                'updated_tasks' => $updatedTasks->count(),
                'new_due_date' => $request->due_date,
            ]);

        } catch (Throwable $e) {
            DB::rollBack();
            return $this->fail('Error in bulk deadline update: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get bulk operation history
     */
    public function getBulkOperationHistory(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $manager = Auth::user();

            $operations = BulkTaskOperation::where('initiated_by', $manager->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->ok('Bulk operation history retrieved successfully', $operations);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving bulk operation history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get bulk operation status
     */
    public function getBulkOperationStatus($id): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $manager = Auth::user();

            $operation = BulkTaskOperation::where('initiated_by', $manager->id)
                ->findOrFail($id);

            return $this->ok('Bulk operation status retrieved successfully', $operation);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving bulk operation status: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Workload Management Methods  ───────────────────── */

    /**
     * Get employee workload capacity
     */
    public function getEmployeeWorkloadCapacity($employeeId): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $employee = Employee::whereIn('department_id', $managedDepartmentIds)
                ->findOrFail($employeeId);

            $weekStart = now()->startOfWeek()->toDateString();

            $workload = EmployeeWorkloadCapacity::where('employee_id', $employeeId)
                ->where('week_start_date', $weekStart)
                ->first();

            if (!$workload) {
                $workload = [
                    'employee_id' => $employeeId,
                    'week_start_date' => $weekStart,
                    'weekly_capacity_hours' => 40,
                    'current_planned_hours' => 0,
                    'workload_percentage' => 0,
                    'workload_status' => 'optimal',
                ];
            }

            return $this->ok('Employee workload capacity retrieved successfully', $workload);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving employee workload capacity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update workload capacity
     */
    public function updateWorkloadCapacity(Request $request, $employeeId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weekly_capacity_hours' => 'required|integer|min:1|max:168',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $employee = Employee::whereIn('department_id', $managedDepartmentIds)
                ->findOrFail($employeeId);

            $weekStart = now()->startOfWeek()->toDateString();

            $workload = EmployeeWorkloadCapacity::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'week_start_date' => $weekStart
                ],
                [
                    'weekly_capacity_hours' => $request->weekly_capacity_hours,
                ]
            );

            // Recalculate percentage and status
            $workload->workload_percentage = ($workload->current_planned_hours / $workload->weekly_capacity_hours) * 100;

            if ($workload->workload_percentage < 70) {
                $workload->workload_status = 'under_utilized';
            } elseif ($workload->workload_percentage <= 100) {
                $workload->workload_status = 'optimal';
            } elseif ($workload->workload_percentage <= 120) {
                $workload->workload_status = 'over_loaded';
            } else {
                $workload->workload_status = 'critical';
            }

            $workload->save();

            return $this->ok('Workload capacity updated successfully', $workload);

        } catch (Throwable $e) {
            return $this->fail('Error updating workload capacity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get workload distribution
     */
    public function getWorkloadDistribution(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->get();

            $distribution = [
                'under_utilized' => 0,
                'optimal' => 0,
                'over_loaded' => 0,
                'critical' => 0,
            ];

            $weekStart = now()->startOfWeek()->toDateString();

            foreach ($employees as $employee) {
                $workload = EmployeeWorkloadCapacity::where('employee_id', $employee->id)
                    ->where('week_start_date', $weekStart)
                    ->first();

                $status = $workload->workload_status ?? 'optimal';
                $distribution[$status]++;
            }

            return $this->ok('Workload distribution retrieved successfully', $distribution);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving workload distribution: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get workload recommendations
     */
    public function getWorkloadRecommendations(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->get();

            $recommendations = [];
            $weekStart = now()->startOfWeek()->toDateString();

            foreach ($employees as $employee) {
                $workload = EmployeeWorkloadCapacity::where('employee_id', $employee->id)
                    ->where('week_start_date', $weekStart)
                    ->first();

                if (!$workload) continue;

                $recommendation = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->getFullNameAttribute(),
                    'current_status' => $workload->workload_status,
                    'current_percentage' => $workload->workload_percentage,
                    'recommendations' => [],
                ];

                switch ($workload->workload_status) {
                    case 'under_utilized':
                        $recommendation['recommendations'][] = 'Consider assigning additional tasks';
                        $recommendation['recommendations'][] = 'Available capacity: ' . (100 - $workload->workload_percentage) . '%';
                        break;
                    case 'over_loaded':
                        $recommendation['recommendations'][] = 'Consider redistributing some tasks';
                        $recommendation['recommendations'][] = 'Overload by: ' . ($workload->workload_percentage - 100) . '%';
                        break;
                    case 'critical':
                        $recommendation['recommendations'][] = 'Urgent: Redistribute tasks immediately';
                        $recommendation['recommendations'][] = 'Critical overload: ' . ($workload->workload_percentage - 100) . '%';
                        break;
                    case 'optimal':
                        $recommendation['recommendations'][] = 'Workload is well balanced';
                        break;
                }

                $recommendations[] = $recommendation;
            }

            return $this->ok('Workload recommendations retrieved successfully', $recommendations);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving workload recommendations: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Analytics and Reporting Methods  ───────────────────── */

    /**
     * Get progress overview
     */
    public function getProgressOverview(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->pluck('id');

            $totalTasks = AssignedTask::whereIn('assigned_to', $employees)->count();
            $completedTasks = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', 'done')->count();
            $overdueTasks = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', '!=', 'done')
                ->where('due_date', '<', now())->count();
            $inProgressTasks = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', 'doing')->count();

            $overview = [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'overdue_tasks' => $overdueTasks,
                'in_progress_tasks' => $inProgressTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                'on_time_completion' => $completedTasks - $overdueTasks,
            ];

            return $this->ok('Progress overview retrieved successfully', $overview);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving progress overview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get department performance metrics
     */
    public function getDepartmentPerformance(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->pluck('id');

            $startDate = $request->get('start_date', now()->subMonth()->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $tasksCompleted = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->count();

            $tasksOnTime = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->whereColumn('completed_at', '<=', 'due_date')
                ->count();

            $averageCompletionTime = AssignedTask::whereIn('assigned_to', $employees)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->selectRaw('AVG(DATEDIFF(completed_at, created_at)) as avg_days')
                ->value('avg_days');

            $performance = [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'tasks_completed' => $tasksCompleted,
                'tasks_on_time' => $tasksOnTime,
                'on_time_rate' => $tasksCompleted > 0 ? round(($tasksOnTime / $tasksCompleted) * 100, 2) : 0,
                'average_completion_days' => round($averageCompletionTime ?? 0, 1),
                'employee_count' => $employees->count(),
            ];

            return $this->ok('Department performance retrieved successfully', $performance);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving department performance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get employee productivity metrics
     */
    public function getEmployeeProductivity($employeeId): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();

            $employee = Employee::whereIn('department_id', $managedDepartmentIds)
                ->findOrFail($employeeId);

            $startDate = now()->subMonth()->toDateString();
            $endDate = now()->toDateString();

            $tasksCompleted = AssignedTask::where('assigned_to', $employeeId)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->count();

            $tasksOnTime = AssignedTask::where('assigned_to', $employeeId)
                ->where('status', 'done')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->whereColumn('completed_at', '<=', 'due_date')
                ->count();

            $currentTasks = AssignedTask::where('assigned_to', $employeeId)
                ->where('status', '!=', 'done')
                ->count();

            $overdueTasks = AssignedTask::where('assigned_to', $employeeId)
                ->where('status', '!=', 'done')
                ->where('due_date', '<', now())
                ->count();

            $productivity = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->getFullNameAttribute(),
                    'department' => $employee->department->name ?? 'Unknown',
                ],
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'tasks_completed' => $tasksCompleted,
                'tasks_on_time' => $tasksOnTime,
                'current_active_tasks' => $currentTasks,
                'overdue_tasks' => $overdueTasks,
                'on_time_rate' => $tasksCompleted > 0 ? round(($tasksOnTime / $tasksCompleted) * 100, 2) : 0,
            ];

            return $this->ok('Employee productivity retrieved successfully', $productivity);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving employee productivity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get task completion trends
     */
    public function getTaskCompletionTrends(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->pluck('id');

            $days = $request->get('days', 30);
            $startDate = now()->subDays($days)->toDateString();

            $trends = [];
            for ($i = $days; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();

                $completed = AssignedTask::whereIn('assigned_to', $employees)
                    ->where('status', 'done')
                    ->whereDate('completed_at', $date)
                    ->count();

                $created = AssignedTask::whereIn('assigned_to', $employees)
                    ->whereDate('created_at', $date)
                    ->count();

                $trends[] = [
                    'date' => $date,
                    'tasks_completed' => $completed,
                    'tasks_created' => $created,
                ];
            }

            return $this->ok('Task completion trends retrieved successfully', $trends);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving task completion trends: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export department report
     */
    public function exportDepartmentReport(Request $request): JsonResponse
    {
        try {
            if (!$this->isDepartmentManager()) {
                return $this->fail('Access denied. Department manager role required.', 403);
            }

            $managedDepartmentIds = $this->getManagedDepartmentIds();
            $employees = Employee::whereIn('department_id', $managedDepartmentIds)->with('department')->get();

            $startDate = $request->get('start_date', now()->subMonth()->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $report = [
                'generated_at' => now()->toISOString(),
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'manager' => Auth::user()->getFullNameAttribute(),
                'departments' => $managedDepartmentIds,
                'summary' => $this->getDepartmentPerformance($request)->getData()->data,
                'employees' => [],
            ];

            foreach ($employees as $employee) {
                $employeeData = $this->getEmployeeProductivity($employee->id)->getData()->data;
                $report['employees'][] = $employeeData;
            }

            return $this->ok('Department report generated successfully', $report);

        } catch (Throwable $e) {
            return $this->fail('Error generating department report: ' . $e->getMessage(), 500);
        }
    }
}
