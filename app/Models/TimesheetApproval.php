<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetApproval extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'timesheet_id',
        'approver_id',
        'approver_role',
        'status',
        'acted_at',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'acted_at' => 'datetime',
    ];

    /**
     * Get the timesheet associated with this approval.
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Get the approver employee.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    /**
     * Check if this approval is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this approval is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if this approval is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Approve this timesheet approval.
     */
    public function approve(?string $comment = null): bool
    {
        $this->status = 'approved';
        $this->acted_at = now();
        $this->comment = $comment;

        return $this->save();
    }

    /**
     * Reject this timesheet approval.
     */
    public function reject(string $comment): bool
    {
        $this->status = 'rejected';
        $this->acted_at = now();
        $this->comment = $comment;

        return $this->save();
    }
}
