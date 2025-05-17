<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'period_start',
        'period_end',
        'overall_status',
        'submitted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the employee that owns this timesheet.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the rows for this timesheet.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(TimesheetRow::class);
    }

    /**
     * Get the approvals for this timesheet.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TimesheetApproval::class);
    }

    /**
     * Get the workflow history for this timesheet.
     */
    public function workflowHistory(): HasMany
    {
        return $this->hasMany(TimesheetWorkflowHistory::class);
    }

    /**
     * Get the chat messages for this timesheet.
     */
    public function chats(): HasMany
    {
        return $this->hasMany(TimesheetChat::class);
    }

    /**
     * Check if this timesheet can be edited.
     *
     * @return bool
     */
    public function canBeEdited(): bool
    {
        return in_array($this->overall_status, ['draft', 'reopened']);
    }

    /**
     * Check if this timesheet can be deleted.
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return $this->overall_status === 'draft';
    }

    /**
     * Calculate total hours for this timesheet.
     *
     * @return float
     */
    public function calculateTotalHours(): float
    {
        return $this->rows->sum('total_hours');
    }
}
