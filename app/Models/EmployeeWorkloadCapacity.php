<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkloadCapacity extends Model
{
    use HasFactory;

    // Specify the correct table name
    protected $table = 'employee_workload_capacity';

    protected $fillable = [
        'employee_id',
        'weekly_capacity_hours',
        'current_planned_hours',
        'week_start_date',
        'workload_percentage',
        'workload_status',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'weekly_capacity_hours' => 'integer',
        'current_planned_hours' => 'integer',
        'workload_percentage' => 'decimal:2',
    ];

    /**
     * Get the employee this workload data belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Scope to get current week workload
     */
    public function scopeCurrentWeek($query)
    {
        $startOfWeek = now()->startOfWeek();
        return $query->where('week_start_date', $startOfWeek);
    }

    /**
     * Scope to filter by workload status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('workload_status', $status);
    }

    /**
     * Scope to get overloaded employees
     */
    public function scopeOverloaded($query)
    {
        return $query->whereIn('workload_status', ['over_loaded', 'critical']);
    }

    /**
     * Calculate and update workload percentage
     */
    public function calculateWorkloadPercentage(): void
    {
        if ($this->weekly_capacity_hours > 0) {
            $this->workload_percentage = ($this->current_planned_hours / $this->weekly_capacity_hours) * 100;
        } else {
            $this->workload_percentage = 0;
        }

        $this->updateWorkloadStatus();
        $this->save();
    }

    /**
     * Update workload status based on percentage
     */
    protected function updateWorkloadStatus(): void
    {
        $percentage = $this->workload_percentage;

        if ($percentage < 70) {
            $this->workload_status = 'under_utilized';
        } elseif ($percentage >= 70 && $percentage <= 100) {
            $this->workload_status = 'optimal';
        } elseif ($percentage > 100 && $percentage <= 120) {
            $this->workload_status = 'over_loaded';
        } else {
            $this->workload_status = 'critical';
        }
    }

    /**
     * Add planned hours to current workload
     */
    public function addPlannedHours(int $hours): void
    {
        $this->current_planned_hours += $hours;
        $this->calculateWorkloadPercentage();
    }

    /**
     * Remove planned hours from current workload
     */
    public function removePlannedHours(int $hours): void
    {
        $this->current_planned_hours = max(0, $this->current_planned_hours - $hours);
        $this->calculateWorkloadPercentage();
    }

    /**
     * Get available capacity in hours
     */
    public function getAvailableCapacity(): int
    {
        return max(0, $this->weekly_capacity_hours - $this->current_planned_hours);
    }

    /**
     * Check if employee can take additional hours
     */
    public function canTakeAdditionalHours(int $hours): bool
    {
        return ($this->current_planned_hours + $hours) <= ($this->weekly_capacity_hours * 1.2); // Allow 120% max
    }

    /**
     * Get workload status color for UI
     */
    public function getStatusColor(): string
    {
        return match ($this->workload_status) {
            'under_utilized' => '#blue',
            'optimal' => '#green',
            'over_loaded' => '#orange',
            'critical' => '#red',
            default => '#gray'
        };
    }

    /**
     * Get workload status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->workload_status) {
            'under_utilized' => 'Under Utilized',
            'optimal' => 'Optimal',
            'over_loaded' => 'Over Loaded',
            'critical' => 'Critical',
            default => 'Unknown'
        };
    }
}
