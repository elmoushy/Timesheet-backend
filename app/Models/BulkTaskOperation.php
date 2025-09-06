<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkTaskOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'initiated_by',
        'operation_type',
        'task_ids',
        'operation_data',
        'status',
        'notes',
        'total_tasks',
        'processed_tasks',
        'failed_tasks',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'task_ids' => 'array',
        'operation_data' => 'array',
        'error_log' => 'array',
        'total_tasks' => 'integer',
        'processed_tasks' => 'integer',
        'failed_tasks' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the manager who initiated this operation
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'initiated_by');
    }

    /**
     * Scope to filter by operation type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('operation_type', $type);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent operations
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Start the operation
     */
    public function start(): void
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        $this->total_tasks = count($this->task_ids);
        $this->save();
    }

    /**
     * Mark operation as completed
     */
    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark operation as failed
     */
    public function fail(array $errors = []): void
    {
        $this->status = 'failed';
        $this->completed_at = now();
        $this->error_log = $errors;
        $this->save();
    }

    /**
     * Update progress
     */
    public function updateProgress(int $processed, int $failed = 0): void
    {
        $this->processed_tasks = $processed;
        $this->failed_tasks = $failed;
        $this->save();
    }

    /**
     * Add error to log
     */
    public function addError(string $taskId, string $error): void
    {
        $errors = $this->error_log ?? [];
        $errors[] = [
            'task_id' => $taskId,
            'error' => $error,
            'timestamp' => now()->toISOString(),
        ];
        $this->error_log = $errors;
        $this->save();
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_tasks == 0) {
            return 0;
        }

        return round(($this->processed_tasks / $this->total_tasks) * 100, 2);
    }

    /**
     * Get operation duration in seconds
     */
    public function getDuration(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();

        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get formatted operation description
     */
    public function getOperationDescription(): string
    {
        $initiatorName = $this->initiator->getFullNameAttribute();

        return match ($this->operation_type) {
            'reassign' => "{$initiatorName} reassigned {$this->total_tasks} task(s)",
            'update_status' => "{$initiatorName} updated status for {$this->total_tasks} task(s)",
            'update_due_date' => "{$initiatorName} updated due dates for {$this->total_tasks} task(s)",
            'update_priority' => "{$initiatorName} updated priority for {$this->total_tasks} task(s)",
            'bulk_delete' => "{$initiatorName} deleted {$this->total_tasks} task(s)",
            default => "{$initiatorName} performed bulk operation on {$this->total_tasks} task(s)"
        };
    }

    /**
     * Check if operation is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if operation completed successfully
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && $this->failed_tasks == 0;
    }

    /**
     * Check if operation has failures
     */
    public function hasFailures(): bool
    {
        return $this->failed_tasks > 0;
    }
}
