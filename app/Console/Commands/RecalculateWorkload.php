<?php

namespace App\Console\Commands;

use App\Models\AssignedTask;
use App\Models\Employee;
use App\Models\EmployeeWorkloadCapacity;
use Illuminate\Console\Command;

class RecalculateWorkload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workload:recalculate {--employee= : Specific employee ID to recalculate} {--week= : Specific week start date (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate employee workload capacity based on assigned tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employeeId = $this->option('employee');
        $weekStart = $this->option('week') ? date('Y-m-d', strtotime($this->option('week'))) : now()->startOfWeek()->toDateString();

        $this->info("Recalculating workload for week starting: {$weekStart}");

        if ($employeeId) {
            $this->recalculateForEmployee($employeeId, $weekStart);
        } else {
            $this->recalculateForAllEmployees($weekStart);
        }

        $this->info('Workload recalculation completed!');
    }

    /**
     * Recalculate workload for a specific employee
     */
    private function recalculateForEmployee(int $employeeId, string $weekStart): void
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            $this->error("Employee with ID {$employeeId} not found.");

            return;
        }

        $this->info("Recalculating workload for: {$employee->getFullNameAttribute()}");

        // Calculate total estimated hours from assigned tasks
        $totalHours = AssignedTask::where('assigned_to', $employeeId)
            ->sum('estimated_hours') ?? 0;

        $this->updateWorkload($employeeId, $weekStart, $totalHours);
    }

    /**
     * Recalculate workload for all active employees
     */
    private function recalculateForAllEmployees(string $weekStart): void
    {
        $employees = Employee::where('user_status', 'active')->get();
        $progressBar = $this->output->createProgressBar($employees->count());
        $progressBar->start();

        foreach ($employees as $employee) {
            // Calculate total estimated hours from assigned tasks
            $totalHours = AssignedTask::where('assigned_to', $employee->id)
                ->sum('estimated_hours') ?? 0;

            $this->updateWorkload($employee->id, $weekStart, $totalHours);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');
        $this->info("Recalculated workload for {$employees->count()} employees");
    }

    /**
     * Update workload record for an employee
     */
    private function updateWorkload(int $employeeId, string $weekStart, int $totalHours): void
    {
        // Find existing workload record
        $workload = EmployeeWorkloadCapacity::where('employee_id', $employeeId)
            ->whereDate('week_start_date', $weekStart)
            ->first();

        // Create new record if it doesn't exist
        if (! $workload) {
            $workload = new EmployeeWorkloadCapacity([
                'employee_id' => $employeeId,
                'week_start_date' => $weekStart,
                'weekly_capacity_hours' => 40,
                'current_planned_hours' => $totalHours,
                'workload_percentage' => 0,
                'workload_status' => 'optimal',
            ]);
        } else {
            // Update existing record
            $workload->current_planned_hours = $totalHours;
        }

        // Recalculate percentage and status
        $workload->workload_percentage = $workload->weekly_capacity_hours > 0
            ? ($workload->current_planned_hours / $workload->weekly_capacity_hours) * 100
            : 0;

        // Update status based on percentage
        if ($workload->workload_percentage < 70) {
            $workload->workload_status = 'under_utilized';
        } elseif ($workload->workload_percentage <= 100) {
            $workload->workload_status = 'optimal';
        } elseif ($workload->workload_percentage <= 120) {
            $workload->workload_status = 'over_loaded';
        } else {
            $workload->workload_status = 'critical';
        }

        $workload->save();
    }
}
