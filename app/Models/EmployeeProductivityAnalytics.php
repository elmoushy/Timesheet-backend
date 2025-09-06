<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProductivityAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'tasks_completed',
        'tasks_created',
        'total_progress_points',
        'hours_logged',
        'streak_days',
        'max_streak',
        'weekly_burndown',
    ];

    protected $casts = [
        'date' => 'date',
        'tasks_completed' => 'integer',
        'tasks_created' => 'integer',
        'total_progress_points' => 'integer',
        'hours_logged' => 'integer',
        'streak_days' => 'integer',
        'max_streak' => 'integer',
        'weekly_burndown' => 'array',
    ];

    /**
     * Get the employee these analytics belong to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Scope to get analytics for a specific date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get current week analytics
     */
    public function scopeCurrentWeek($query)
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        return $query->whereBetween('date', [$startOfWeek, $endOfWeek]);
    }

    /**
     * Scope to get current month analytics
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year);
    }

    /**
     * Calculate productivity score based on multiple factors
     */
    public function getProductivityScore(): float
    {
        $score = 0;

        // Tasks completed weight: 40%
        $score += ($this->tasks_completed * 10) * 0.4;

        // Progress points weight: 30%
        $score += ($this->total_progress_points / 10) * 0.3;

        // Hours efficiency weight: 20%
        if ($this->hours_logged > 0) {
            $efficiency = $this->tasks_completed / $this->hours_logged;
            $score += ($efficiency * 100) * 0.2;
        }

        // Streak bonus weight: 10%
        $score += ($this->streak_days * 2) * 0.1;

        return round(min($score, 100), 2);
    }

    /**
     * Update streak counter
     */
    public function updateStreak(): void
    {
        if ($this->tasks_completed > 0) {
            $this->streak_days += 1;
            if ($this->streak_days > $this->max_streak) {
                $this->max_streak = $this->streak_days;
            }
        } else {
            $this->streak_days = 0;
        }
        $this->save();
    }

    /**
     * Get weekly burndown chart data
     */
    public function getWeeklyBurndownData(): array
    {
        return $this->weekly_burndown ?? [];
    }

    /**
     * Update weekly burndown data
     */
    public function updateWeeklyBurndown(array $burndownData): void
    {
        $this->weekly_burndown = $burndownData;
        $this->save();
    }
}
