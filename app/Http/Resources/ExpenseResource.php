<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
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
            'employee_name' => $this->employee->first_name . ' ' . $this->employee->last_name,
            'employee_email' => $this->employee->work_email,
            'employee_department' => $this->when(
                $this->employee->department,
                $this->employee->department?->department_name
            ),
            'employee_manager' => $this->when(
                $this->employee->supervisor,
                $this->employee->supervisor?->first_name . ' ' . $this->employee->supervisor?->last_name
            ),
            'title' => $this->title,
            'expenses' => ExpenseItemResource::collection($this->expenseItems),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $this->when(
                $this->reviewer,
                $this->reviewer?->first_name . ' ' . $this->reviewer?->last_name
            ),
            'rejection_reason' => $this->rejection_reason,
            'return_reason' => $this->return_reason,
            'total_amount_egp' => (float) $this->total_amount_egp,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
