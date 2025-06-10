<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'assigned_to',
        'assigned_by',
        'status',
        'progress_points',
        'is_pinned',
        'is_important',
        'notes',
        'assignment_notes',
        'status_notes',
        'deadline_notes',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'permission_level',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_important' => 'boolean',
        'due_date' => 'date',
        'progress_points' => 'integer',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the master task this assignment is based on
     */
    public function masterTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Get the employee this task is assigned to
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * Get the manager who assigned this task
     */
    public function assignedByManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * Alias for masterTask() relationship - used in TimeManagementController
     */
    public function task(): BelongsTo
    {
        return $this->masterTask();
    }

    /**
     * Alias for assignedByManager() relationship - used in TimeManagementController
     */
    public function assignedBy(): BelongsTo
    {
        return $this->assignedByManager();
    }

    /**
     * Scope to get important tasks
     */
    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    /**
     * Scope to get pinned tasks
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by permission level
     */
    public function scopeByPermission($query, $permission)
    {
        return $query->where('permission_level', $permission);
    }

    /**
     * Scope to get overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'done');
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date < now() && $this->status !== 'done';
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->status === 'done') {
            return 100;
        }

        return min($this->progress_points, 100);
    }

    /**
     * Check if employee can edit this task based on permission level
     */
    public function canEdit(): bool
    {
        return $this->permission_level === 'full_edit';
    }

    /**
     * Check if employee can edit progress
     */
    public function canEditProgress(): bool
    {
        return in_array($this->permission_level, ['edit_progress', 'full_edit']);
    }

    /**
     * Check if employee can only view
     */
    public function isViewOnly(): bool
    {
        return $this->permission_level === 'view_only';
    }

    /**
     * Toggle important status
     */
    public function toggleImportant(): bool
    {
        $this->is_important = !$this->is_important;
        return $this->save();
    }

    /**
     * Toggle pinned status
     */
    public function togglePinned(): bool
    {
        $this->is_pinned = !$this->is_pinned;
        return $this->save();
    }

    /**
     * Update status if allowed
     */
    public function updateStatus(string $newStatus): bool
    {
        if (!$this->canEditProgress()) {
            return false;
        }

        $this->status = $newStatus;
        return $this->save();
    }

    /**
     * Update progress points if allowed
     */
    public function updateProgress(int $points): bool
    {
        if (!$this->canEditProgress()) {
            return false;
        }

        $this->progress_points = min(max($points, 0), 100);
        return $this->save();
    }
}
