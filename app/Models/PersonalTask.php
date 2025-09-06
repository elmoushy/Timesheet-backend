<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'status',
        'progress_points',
        'is_pinned',
        'is_important',
        'notes',
        'due_date',
        'estimated_hours',
        'actual_hours',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_important' => 'boolean',
        'due_date' => 'date',
        'progress_points' => 'integer',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
    ];

    /**
     * Get the employee who owns this task
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
     * Toggle important status
     */
    public function toggleImportant(): bool
    {
        $this->is_important = ! $this->is_important;

        return $this->save();
    }

    /**
     * Toggle pinned status
     */
    public function togglePinned(): bool
    {
        $this->is_pinned = ! $this->is_pinned;

        return $this->save();
    }
}
