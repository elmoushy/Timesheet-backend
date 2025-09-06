<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ProjectEmployeeAssignment;
use App\Models\Task;

echo "=== Debugging Task Assignment Issue ===\n\n";

// Check all tasks
echo "=== All Tasks ===\n";
$tasks = Task::all();
foreach ($tasks as $task) {
    echo 'Task ID: '.$task->id.', Name: '.$task->name.', Project ID: '.$task->project_id."\n";
}
echo "\n";

// Check project assignments for project 1 (where George is assigned)
echo "=== Project Assignments for Project 1 ===\n";
$assignments1 = ProjectEmployeeAssignment::where('project_id', 1)
    ->with('employee')
    ->get();

foreach ($assignments1 as $assignment) {
    echo 'Employee: '.$assignment->employee->first_name.' '.$assignment->employee->last_name;
    echo ' (ID: '.$assignment->employee_id.')';
    echo ' - Status: '.$assignment->department_approval_status."\n";
}
echo "\n";

// Check project assignments for project 3 (where task 2 belongs)
echo "=== Project Assignments for Project 3 ===\n";
$assignments3 = ProjectEmployeeAssignment::where('project_id', 3)
    ->with('employee')
    ->get();

if ($assignments3->count() == 0) {
    echo "No employees assigned to Project 3\n";
} else {
    foreach ($assignments3 as $assignment) {
        echo 'Employee: '.$assignment->employee->first_name.' '.$assignment->employee->last_name;
        echo ' (ID: '.$assignment->employee_id.')';
        echo ' - Status: '.$assignment->department_approval_status."\n";
    }
}
echo "\n";

// Check which tasks belong to project 1
echo "=== Tasks in Project 1 ===\n";
$project1Tasks = Task::where('project_id', 1)->get();
foreach ($project1Tasks as $task) {
    echo 'Task ID: '.$task->id.', Name: '.$task->name."\n";
}

if ($project1Tasks->count() == 0) {
    echo "No tasks found in Project 1\n";
}
