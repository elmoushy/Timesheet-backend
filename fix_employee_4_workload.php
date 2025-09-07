<?php

requir// Get all assigned tasks for this employee excluding blocked tasks
$assignedTasks = AssignedTask::where('assigned_to', $employeeId)
    ->whereIn('status', ['to-do', 'doing']) // Only count active tasks, exclude blocked
    ->get();ce 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AssignedTask;
use App\Models\EmployeeWorkloadCapacity;

echo "=== Fixing Workload for Employee ID 4 ===\n\n";

$employeeId = 4;

// Get all assigned tasks for this employee
$assignedTasks = AssignedTask::where('assigned_to', $employeeId)
    ->whereIn('status', ['to-do', 'doing']) // Only count active tasks
    ->get();

$totalHours = 0;
foreach ($assignedTasks as $task) {
    $totalHours += $task->estimated_hours ?? 0;
    echo "Task ID {$task->task_id}: {$task->estimated_hours} hours (Status: {$task->status})\n";
}

echo "Total hours to be assigned: {$totalHours}\n\n";

// Get or create workload record
$weekStart = now()->startOfWeek()->toDateString();
$workload = EmployeeWorkloadCapacity::where('employee_id', $employeeId)
    ->whereDate('week_start_date', $weekStart)
    ->first();

if ($workload) {
    echo "Found existing workload record:\n";
    echo "Current planned hours: {$workload->current_planned_hours}\n";
    echo "Current status: {$workload->workload_status}\n\n";

    // Update the planned hours to match assigned tasks
    $workload->current_planned_hours = $totalHours;
    $workload->workload_percentage = ($workload->current_planned_hours / $workload->weekly_capacity_hours) * 100;

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

    echo "Updated workload record:\n";
    echo "New planned hours: {$workload->current_planned_hours}\n";
    echo "New percentage: {$workload->workload_percentage}%\n";
    echo "New status: {$workload->workload_status}\n";
} else {
    echo "No workload record found for current week\n";
}
