<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetWorkflowHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'timesheet_workflow_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'timesheet_id',
        'stage',
        'action',
        'comment',
        'acted_by',
        'acted_at',
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
     * Get the timesheet associated with this workflow history entry.
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Get the employee who performed the action.
     */
    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by');
    }

    /**
     * Alias for actionBy to maintain backward compatibility.
     */
    public function actor(): BelongsTo
    {
        return $this->actionBy();
    }

    /**
     * Create a new workflow history entry.
     *
     * @param int $timesheetId
     * @param string $stage
     * @param string $action
     * @param int $actedBy
     * @param string|null $comment
     * @return self
     */
    public static function createEntry(
        int $timesheetId,
        string $stage,
        string $action,
        int $actedBy,
        ?string $comment = null
    ): self {
        return self::create([
            'timesheet_id' => $timesheetId,
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment,
            'acted_by' => $actedBy,
            'acted_at' => now(),
        ]);
    }
}
