<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For search endpoints, return minimal data
        if ($request->routeIs('employees.search') || $request->routeIs('projects.employeedropdown')) {
            return [
                'id' => $this->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'job_title' => $this->job_title,
                'display_text' => $this->first_name . ' ' . $this->last_name . ' (' . $this->job_title . ')',
            ];
        }

        // For full employee endpoints, return all required fields
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'department_id' => $this->department_id,
            'employee_code' => $this->employee_code ?? null,
            'image_path' => $this->image_path ?? null,
            // Add other fields as needed based on specific endpoints
        ];
    }
}
