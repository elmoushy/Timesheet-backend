<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_type',
        'task_id',
        'employee_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'notes',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    /**
     * Get the employee who performed this action
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the related task based on task_type
     */
    public function getTaskAttribute()
    {
        return match ($this->task_type) {
            'personal' => PersonalTask::find($this->task_id),
            'project' => ProjectTask::find($this->task_id),
            'assigned' => AssignedTask::find($this->task_id),
            default => null
        };
    }

    /**
     * Scope to filter by task type
     */
    public function scopeByTaskType($query, $taskType)
    {
        return $query->where('task_type', $taskType);
    }

    /**
     * Scope to filter by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get recent activities
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get activities for a specific employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Create activity log entry
     */
    public static function logActivity(
        string $taskType,
        int $taskId,
        int $employeeId,
        string $action,
        ?string $fieldChanged = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $notes = null
    ): self {
        return self::create([
            'task_type' => $taskType,
            'task_id' => $taskId,
            'employee_id' => $employeeId,
            'action' => $action,
            'field_changed' => $fieldChanged,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes,
            'performed_at' => now(),
        ]);
    }

    /**
     * Get formatted action description
     */
    public function getActionDescription(): string
    {
        $employeeName = $this->employee->getFullNameAttribute();

        return match ($this->action) {
            'created' => "{$employeeName} created a new {$this->task_type} task",
            'updated' => "{$employeeName} updated {$this->field_changed} from '{$this->old_value}' to '{$this->new_value}'",
            'status_changed' => "{$employeeName} changed status from '{$this->old_value}' to '{$this->new_value}'",
            'pinned' => "{$employeeName} pinned this task",
            'unpinned' => "{$employeeName} unpinned this task",
            'marked_important' => "{$employeeName} marked this task as important",
            'unmarked_important' => "{$employeeName} removed importance from this task",
            'completed' => "{$employeeName} completed this task",
            'blocked' => "{$employeeName} blocked this task",
            default => "{$employeeName} performed action: {$this->action}"
        };
    }

    /**
     * Get activity icon for UI
     */
    public function getActivityIcon(): string
    {
        return match ($this->action) {
            'created' => 'plus-circle',
            'updated' => 'edit',
            'status_changed' => 'arrow-right',
            'pinned' => 'pin',
            'unpinned' => 'pin-off',
            'marked_important' => 'star',
            'unmarked_important' => 'star-off',
            'completed' => 'check-circle',
            'blocked' => 'x-circle',
            default => 'activity'
        };
    }
}
