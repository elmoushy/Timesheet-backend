<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'name' => $this->name,
            'task_type' => $this->task_type,
            'department_id' => $this->department_id,
            'project_id' => $this->project_id,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Related entities - only the required fields
            'department' => $this->when(isset($this->department), function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'display_text' => $this->department->display_text ?? null,
                ];
            }),

            'project' => $this->when(isset($this->project), function () {
                return [
                    'id' => $this->project->id,
                    'project_name' => $this->project->project_name,
                    'description' => $this->project->description ?? null,
                    'department_id' => $this->project->department_id,
                    'client_id' => $this->project->client_id,
                    'status' => $this->project->status ?? null,
                    'start_date' => $this->project->start_date,
                    'end_date' => $this->project->end_date,
                    'created_at' => $this->project->created_at,
                    'updated_at' => $this->project->updated_at,
                ];
            }),

            'display_text' => $this->display_text ?? null,
        ];
    }
}
