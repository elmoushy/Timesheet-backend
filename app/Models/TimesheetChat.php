<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimesheetChat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'timesheet_id',
        'parent_id',
        'sender_id',
        'sender_role',
        'message',
    ];

    /**
     * Get the timesheet associated with this chat message.
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Get the parent chat message if this is a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TimesheetChat::class, 'parent_id');
    }

    /**
     * Get the replies to this chat message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TimesheetChat::class, 'parent_id');
    }

    /**
     * Get the employee who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sender_id');
    }

    /**
     * Get only root-level messages (not replies).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRootMessages($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get messages with their replies.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithReplies($query)
    {
        return $query->with('replies.sender');
    }
}
