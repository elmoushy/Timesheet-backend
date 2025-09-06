<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AssignedTask;

echo "=== Checking Assigned Tasks for Employee 4 ===\n\n";

$assignedTasks = AssignedTask::where('assigned_to', 4)
    ->with('task')
    ->get();

echo 'Found '.$assignedTasks->count()." assigned tasks:\n";

foreach ($assignedTasks as $assignedTask) {
    echo 'Task ID: '.$assignedTask->task_id.
         ', Task Name: '.($assignedTask->task ? $assignedTask->task->name : 'N/A').
         ', Estimated Hours: '.$assignedTask->estimated_hours.
         ', Status: '.$assignedTask->status."\n";
}

if ($assignedTasks->count() > 0) {
    echo "\nYou can test removing task assignment with:\n";
    echo 'Task ID: '.$assignedTasks->first()->task_id."\n";
    echo "Employee ID: 4\n";
}
