<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Employee;
use App\Models\Task;

echo "=== Testing the Logic for Task ID 1 ===\n\n";

// Simulate the method logic for task_id=1
$taskId = 1;
$task = Task::find($taskId);

if (! $task) {
    echo "Task not found\n";
    exit;
}

echo 'Task ID: '.$task->id."\n";
echo 'Project ID: '.$task->project_id."\n\n";

// Get managed department IDs (assuming we're testing as employee 2 - Seif who manages department 2)
$managedDepartmentIds = [1, 2]; // Simulate both departments for testing

// Apply the same logic as the updated method
$employees = Employee::whereIn('department_id', $managedDepartmentIds)
    ->where('user_status', 'active')
    ->whereHas('projectAssignments', function ($query) use ($task) {
        $query->where('project_id', $task->project_id)
            ->where('department_approval_status', 'approved');
    })
    ->select('id', 'first_name', 'middle_name', 'last_name', 'work_email', 'department_id')
    ->with('department:id,name')
    ->get()
    ->map(function ($employee) {
        return [
            'id' => $employee->id,
            'name' => $employee->first_name.' '.$employee->last_name,
            'email' => $employee->work_email,
            'department_name' => $employee->department->name ?? 'Unknown',
        ];
    });

echo "=== Results for Task ID 1 ===\n";
if ($employees->count() > 0) {
    foreach ($employees as $employee) {
        echo 'Employee: '.$employee['name'].' (ID: '.$employee['id'].")\n";
        echo 'Email: '.$employee['email']."\n";
        echo 'Department: '.$employee['department_name']."\n\n";
    }
} else {
    echo "No eligible employees found\n";
}
