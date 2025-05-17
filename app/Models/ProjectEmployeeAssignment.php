<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEmployeeAssignment extends Model
{
    protected $table = 'xxx_project_employee_assignments';

    protected $fillable = [
        'project_id',
        'employee_id',
        'department_approval_status',
        'requested_by',
        'approved_by',
        'requested_at',
        'response_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'response_at' => 'datetime',
        'department_approval_status' => 'string',
    ];

    /**
     * Get the project that owns the assignment.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the employee being assigned.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the employee who requested the assignment.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    /**
     * Get the employee who approved/rejected the assignment.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Check if the assignment is pending approval.
     */
    public function isPending(): bool
    {
        return $this->department_approval_status === 'pending';
    }

    /**
     * Check if the assignment is approved.
     */
    public function isApproved(): bool
    {
        return $this->department_approval_status === 'approved';
    }

    /**
     * Check if the assignment is rejected.
     */
    public function isRejected(): bool
    {
        return $this->department_approval_status === 'rejected';
    }

    /**
     * Approve the assignment.
     */
    public function approve(int $approverId, ?string $notes = null): bool
    {
        $this->department_approval_status = 'approved';
        $this->approved_by = $approverId;
        $this->response_at = now();

        if ($notes) {
            $this->notes = $notes;
        }

        return $this->save();
    }

    /**
     * Reject the assignment.
     */
    public function reject(int $rejecterId, ?string $notes = null): bool
    {
        $this->department_approval_status = 'rejected';
        $this->approved_by = $rejecterId;
        $this->response_at = now();

        if ($notes) {
            $this->notes = $notes;
        }

        return $this->save();
    }
}
