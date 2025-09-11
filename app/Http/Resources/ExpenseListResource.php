<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseListResource extends JsonResource
{
    /**
     * Transform the resource into an array for optimized list view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee->first_name . ' ' . $this->employee->last_name,
            'employee_email' => $this->employee->work_email,
            'department' => $this->when(
                $this->employee->department,
                $this->employee->department?->department_name
            ),
            'title' => $this->title,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'items_count' => $this->expenseItems->count(),
            'currencies' => $this->expenseItems->pluck('currency')->unique()->values(),
            'total_amount_egp' => (float) $this->total_amount_egp,
            'has_attachments' => $this->expenseItems->whereNotNull('attachment_blob')->count() > 0,
        ];
    }
}
