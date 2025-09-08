<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'overall_status' => $this->overall_status,
            'submitted_at' => $this->submitted_at ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'employee' => $this->when(isset($this->employee), function () {
                return [
                    'id' => $this->employee->id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'email' => $this->employee->email ?? null,
                ];
            }),

            'rows' => $this->when(isset($this->rows), function () {
                return $this->rows->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'timesheet_id' => $row->timesheet_id ?? null,
                        'project_id' => $row->project_id,
                        'task_id' => $row->task_id,
                        'hours_monday' => $row->hours_monday,
                        'hours_tuesday' => $row->hours_tuesday,
                        'hours_wednesday' => $row->hours_wednesday,
                        'hours_thursday' => $row->hours_thursday,
                        'hours_friday' => $row->hours_friday,
                        'hours_saturday' => $row->hours_saturday,
                        'hours_sunday' => $row->hours_sunday,
                        'total_hours' => $row->total_hours ?? ($row->hours_monday + $row->hours_tuesday + $row->hours_wednesday + $row->hours_thursday + $row->hours_friday + $row->hours_saturday + $row->hours_sunday),
                        'achievement_note' => $row->achievement_note,
                        'project' => isset($row->project) ? [
                            'id' => $row->project->id,
                            'name' => $row->project->project_name,
                        ] : null,
                        'task' => isset($row->task) ? [
                            'id' => $row->task->id,
                            'name' => $row->task->name,
                        ] : null,
                    ];
                })->toArray();
            }) ?: [],

            'approvals' => $this->when(isset($this->approvals), function () {
                return $this->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'timesheet_id' => $approval->timesheet_id,
                        'approver_id' => $approval->approver_id,
                        'approver_role' => $approval->approver_role,
                        'status' => $approval->status,
                        'acted_at' => $approval->acted_at,
                        'comment' => $approval->comment,
                        'created_at' => $approval->created_at,
                        'updated_at' => $approval->updated_at,
                        'approver' => isset($approval->approver) ? [
                            'id' => $approval->approver->id,
                            'first_name' => $approval->approver->first_name,
                            'last_name' => $approval->approver->last_name,
                            'email' => $approval->approver->email,
                        ] : null,
                    ];
                })->toArray();
            }) ?: [],
        ];
    }
}
