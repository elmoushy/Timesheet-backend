<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeManagementDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'personal_tasks' => $this->resource['personal_tasks']->map(function ($task) {
                return [
                    'id' => $task->id,
                    'employee_id' => $task->employee_id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'progress_points' => $task->progress_points,
                    'due_date' => $task->due_date,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'is_important' => $task->is_important,
                    'is_pinned' => $task->is_pinned,
                    'notes' => $task->notes,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            }),

            'project_tasks' => $this->resource['project_tasks']->map(function ($task) {
                return [
                    'id' => $task->id,
                    'employee_id' => $task->employee_id,
                    'project_assignment_id' => $task->project_assignment_id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'progress_points' => $task->progress_points,
                    'due_date' => $task->due_date,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'is_important' => $task->is_important,
                    'is_pinned' => $task->is_pinned,
                    'project_assignment' => [
                        'project' => [
                            'id' => $task->projectAssignment->project->id,
                            'name' => $task->projectAssignment->project->project_name,
                            'project_name' => $task->projectAssignment->project->project_name,
                            'description' => $task->projectAssignment->project->description ?? null,
                        ],
                    ],
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            }),

            'assigned_tasks' => $this->resource['assigned_tasks']->map(function ($task) {
                return [
                    'id' => $task->id,
                    'task_id' => $task->task_id,
                    'assigned_to' => $task->assigned_to,
                    'assigned_by' => $task->assigned_by,
                    'status' => $task->status,
                    'progress_points' => $task->progress_points,
                    'due_date' => $task->due_date,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'permission_level' => $task->permission_level,
                    'is_important' => $task->is_important,
                    'is_pinned' => $task->is_pinned,
                    'task' => [
                        'id' => $task->task->id,
                        'title' => $task->task->title ?? $task->task->name,
                        'description' => $task->task->description,
                    ],
                    'assigned_by_user' => [
                        'id' => $task->assignedBy->id,
                        'first_name' => $task->assignedBy->first_name,
                        'last_name' => $task->assignedBy->last_name,
                    ],
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            }),

            'important_tasks' => [
                'personal' => $this->resource['important_tasks']['personal']->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'employee_id' => $task->employee_id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'progress_points' => $task->progress_points,
                        'due_date' => $task->due_date,
                        'estimated_hours' => $task->estimated_hours,
                        'actual_hours' => $task->actual_hours,
                        'is_important' => $task->is_important,
                        'is_pinned' => $task->is_pinned,
                        'notes' => $task->notes,
                        'created_at' => $task->created_at,
                        'updated_at' => $task->updated_at,
                    ];
                }),
                'project' => $this->resource['important_tasks']['project']->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'employee_id' => $task->employee_id,
                        'project_assignment_id' => $task->project_assignment_id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'progress_points' => $task->progress_points,
                        'due_date' => $task->due_date,
                        'estimated_hours' => $task->estimated_hours,
                        'actual_hours' => $task->actual_hours,
                        'is_important' => $task->is_important,
                        'is_pinned' => $task->is_pinned,
                        'project_assignment' => [
                            'project' => [
                                'id' => $task->projectAssignment->project->id,
                                'name' => $task->projectAssignment->project->project_name,
                                'project_name' => $task->projectAssignment->project->project_name,
                                'description' => $task->projectAssignment->project->description ?? null,
                            ],
                        ],
                        'created_at' => $task->created_at,
                        'updated_at' => $task->updated_at,
                    ];
                }),
                'assigned' => $this->resource['important_tasks']['assigned']->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'task_id' => $task->task_id,
                        'assigned_to' => $task->assigned_to,
                        'assigned_by' => $task->assigned_by,
                        'status' => $task->status,
                        'progress_points' => $task->progress_points,
                        'due_date' => $task->due_date,
                        'estimated_hours' => $task->estimated_hours,
                        'actual_hours' => $task->actual_hours,
                        'permission_level' => $task->permission_level,
                        'is_important' => $task->is_important,
                        'is_pinned' => $task->is_pinned,
                        'task' => [
                            'id' => $task->task->id,
                            'title' => $task->task->title ?? $task->task->name,
                            'description' => $task->task->description,
                        ],
                        'assigned_by_user' => [
                            'id' => $task->assignedBy->id,
                            'first_name' => $task->assignedBy->first_name,
                            'last_name' => $task->assignedBy->last_name,
                        ],
                        'created_at' => $task->created_at,
                        'updated_at' => $task->updated_at,
                    ];
                }),
            ],

            'analytics' => [
                'current_streak' => $this->resource['analytics']['current_streak'],
                'max_streak' => $this->resource['analytics']['max_streak'],
                'total_tasks_completed' => $this->resource['analytics']['total_tasks_completed'] ?? 0,
                'total_hours_logged' => $this->resource['analytics']['total_hours_logged'] ?? 0,
                'average_daily_tasks' => $this->resource['analytics']['average_daily_tasks'] ?? 0,
                'productivity_trend' => $this->resource['analytics']['productivity_trend'] ?? 'stable',
                'weekly_data' => $this->resource['analytics']['weekly_data']->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'employee_id' => $item->employee_id,
                        'date' => $item->date,
                        'tasks_completed' => $item->tasks_completed,
                        'tasks_created' => $item->tasks_created,
                        'total_progress_points' => $item->total_progress_points,
                        'hours_logged' => $item->hours_logged,
                        'streak_days' => $item->streak_days,
                        'max_streak' => $item->max_streak,
                    ];
                }),
                'daily_data' => $this->resource['analytics']['daily_data'] ?? [],
            ],

            'summary' => [
                'total_tasks' => $this->resource['summary']['total_tasks'],
                'completed_today' => $this->resource['summary']['completed_today'],
                'overdue_tasks' => $this->resource['summary']['overdue_tasks'],
                'due_this_week' => $this->resource['summary']['due_this_week'],
            ],
        ];
    }
}
