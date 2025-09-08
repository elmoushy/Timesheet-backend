<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'alias' => $this->alias,
            'region' => $this->region,
            'address' => $this->address,
            'business_sector' => $this->business_sector,
            'notes' => $this->notes,
            'contact_numbers' => $this->when(isset($this->contactNumbers), function () {
                return $this->contactNumbers->map(function ($contactNumber) {
                    return [
                        'id' => $contactNumber->id ?? null,
                        'client_id' => $contactNumber->client_id ?? null,
                        'name' => $contactNumber->name,
                        'number' => $contactNumber->number,
                        'type' => $contactNumber->type,
                        'is_primary' => $contactNumber->is_primary,
                    ];
                })->toArray();
            }) ?: null,
        ];
    }
}
