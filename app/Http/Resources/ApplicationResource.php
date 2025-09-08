<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'description' => $this->description,
            'department_id' => $this->department_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'department' => $this->when(isset($this->department), function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
        ];
    }
}
