<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetRow extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'timesheet_id',
        'project_id',
        'task_id',
        'hours_monday',
        'hours_tuesday',
        'hours_wednesday',
        'hours_thursday',
        'hours_friday',
        'hours_saturday',
        'hours_sunday',
        'achievement_note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hours_monday' => 'float',
        'hours_tuesday' => 'float',
        'hours_wednesday' => 'float',
        'hours_thursday' => 'float',
        'hours_friday' => 'float',
        'hours_saturday' => 'float',
        'hours_sunday' => 'float',
        'total_hours' => 'float',
    ];

    /**
     * Get the timesheet that owns this row.
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Get the project associated with this row.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the task associated with this row.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Calculate total hours for this row.
     * Note: This would typically be handled by a database computed column,
     * but we provide this method for flexibility.
     */
    public function calculateTotalHours(): float
    {
        return $this->hours_monday +
               $this->hours_tuesday +
               $this->hours_wednesday +
               $this->hours_thursday +
               $this->hours_friday +
               $this->hours_saturday +
               $this->hours_sunday;
    }
}
