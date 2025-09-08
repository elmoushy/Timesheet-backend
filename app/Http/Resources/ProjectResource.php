<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'client_id' => $this->client_id,
            'project_name' => $this->project_name,
            'department_id' => $this->department_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'project_manager_id' => $this->project_manager_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Related entities - only the required fields
            'client' => $this->when(isset($this->client), function () {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'alias' => $this->client->alias,
                ];
            }),

            'department' => $this->when(isset($this->department), function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),

            'manager' => $this->when(isset($this->manager), function () {
                return [
                    'id' => $this->manager->id,
                    'first_name' => $this->manager->first_name,
                    'last_name' => $this->manager->last_name,
                ];
            }),

            // These should be arrays or null as per API requirements
            'managers' => $this->when(isset($this->managers), function () {
                return $this->managers->map(function ($manager) {
                    return [
                        'id' => $manager->id,
                        'first_name' => $manager->first_name,
                        'last_name' => $manager->last_name,
                    ];
                })->toArray();
            }) ?: null,

            'products' => $this->when(isset($this->products), function () {
                return $this->products->pluck('id')->toArray();
            }) ?: null,

            'contact_numbers' => $this->when(isset($this->contactNumbers), function () {
                return $this->contactNumbers->map(function ($contactNumber) {
                    return [
                        'id' => $contactNumber->id,
                        'client_id' => $contactNumber->client_id,
                        'project_id' => $contactNumber->project_id,
                        'name' => $contactNumber->name,
                        'number' => $contactNumber->number,
                        'type' => $contactNumber->type,
                        'is_primary' => $contactNumber->is_primary,
                        'created_at' => $contactNumber->created_at,
                        'updated_at' => $contactNumber->updated_at,
                    ];
                })->toArray();
            }) ?: null,
        ];
    }
}
