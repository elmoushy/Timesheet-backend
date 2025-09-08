<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For dropdown endpoints, return minimal data
        if ($request->routeIs('projects.departments') || $request->routeIs('tasks.departments') || $request->routeIs('applications.departments')) {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'display_text' => $this->name,
            ];
        }

        // For full department endpoints
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
