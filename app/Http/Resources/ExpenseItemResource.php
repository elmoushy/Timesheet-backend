<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseItemResource extends JsonResource
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
            'date' => $this->date->format('Y-m-d'),
            'type' => $this->type,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'currency_rate' => (float) $this->currency_rate,
            'description' => $this->description,
            'attachment' => $this->attachment_data_uri,
        ];
    }
}
