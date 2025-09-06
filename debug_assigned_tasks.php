<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AssignedTask;
use App\Models\EmployeeWorkloadCapacity;

echo "=== Debugging Assigned Tasks for Employee ID 4 ===\n\n";

// Check all assigned tasks for employee ID 4
$assignedTasks = AssignedTask::where('assigned_to', 4)->with(['task'])->get();
echo 'Found '.$assignedTasks->count()." assigned tasks:\n";

$totalEstimatedHours = 0;
foreach ($assignedTasks as $task) {
    echo 'Task ID: '.$task->task_id.
         ', Status: '.$task->status.
         ', Estimated Hours: '.$task->estimated_hours.
         ', Due Date: '.$task->due_date."\n";
    $totalEstimatedHours += $task->estimated_hours ?? 0;
}
echo "\nTotal Estimated Hours: ".$totalEstimatedHours."\n\n";

// Check the workload record
echo "=== Workload Record Investigation ===\n";
$weekStart = now()->startOfWeek()->toDateString();
echo 'Current week start (date string): '.$weekStart."\n";
echo 'Current week start (datetime): '.now()->startOfWeek()->format('Y-m-d H:i:s')."\n\n";

// Try different query methods
echo "Method 1: Using where('week_start_date', \$weekStart)\n";
$workload1 = EmployeeWorkloadCapacity::where('employee_id', 4)
    ->where('week_start_date', $weekStart)
    ->first();
echo $workload1 ? 'FOUND: Hours = '.$workload1->current_planned_hours : 'NOT FOUND';
echo "\n\n";

echo "Method 2: Using whereDate('week_start_date', \$weekStart)\n";
$workload2 = EmployeeWorkloadCapacity::where('employee_id', 4)
    ->whereDate('week_start_date', $weekStart)
    ->first();
echo $workload2 ? 'FOUND: Hours = '.$workload2->current_planned_hours : 'NOT FOUND';
echo "\n\n";

echo "Method 3: Raw comparison\n";
$allRecords = EmployeeWorkloadCapacity::where('employee_id', 4)->get();
foreach ($allRecords as $record) {
    echo 'DB Record: '.$record->week_start_date.' vs Query: '.$weekStart."\n";
    echo 'Equal? '.($record->week_start_date == $weekStart ? 'YES' : 'NO')."\n";
    echo 'Date Equal? '.(\Carbon\Carbon::parse($record->week_start_date)->toDateString() == $weekStart ? 'YES' : 'NO')."\n\n";
}
