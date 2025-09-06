<?php

require_once __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Project Employees Endpoint ===\n";

// Test the getEmployeesForProject method
$project = \App\Models\Project::find(1);
if ($project) {
    echo "Testing with project: {$project->project_name}\n\n";

    // Get all active employees
    $employees = \App\Models\Employee::where('user_status', 'active')
        ->with(['department', 'projectAssignments' => function ($query) {
            $query->where('project_id', 1);
        }])
        ->get();

    echo "Found {$employees->count()} active employees:\n";

    foreach ($employees as $employee) {
        $assignment = $employee->projectAssignments->first();

        echo "- {$employee->getFullNameAttribute()}\n";
        echo '  Department: '.($employee->department ? $employee->department->name : 'Unknown')."\n";

        if ($assignment) {
            echo "  ✅ ASSIGNED to project\n";
            echo "  Status: {$assignment->department_approval_status}\n";
            echo "  Assigned at: {$assignment->created_at}\n";
        } else {
            $needsApproval = $employee->department_id !== $project->department_id;
            echo "  ❌ NOT assigned to project\n";
            echo '  Would need approval: '.($needsApproval ? 'Yes' : 'No')."\n";
        }
        echo "\n";
    }
} else {
    echo "Project not found!\n";
}

echo "\n=== Testing Task Assignment Endpoint ===\n";

$task = \App\Models\Task::find(1);
if ($task) {
    echo "Testing with task: {$task->name}\n";
    echo "Task project ID: {$task->project_id}\n\n";

    // Get assigned users for this task
    $assignedTasks = \App\Models\AssignedTask::where('task_id', 1)
        ->with('assignedEmployee')
        ->get();

    echo "Found {$assignedTasks->count()} task assignments:\n";

    foreach ($assignedTasks as $assignment) {
        if ($assignment->assignedEmployee) {
            $employee = $assignment->assignedEmployee;
            echo "- {$employee->getFullNameAttribute()}\n";
            echo "  Task Status: {$assignment->status}\n";
            echo '  Department: '.($employee->department ? $employee->department->name : 'Unknown')."\n";

            // Check project assignment
            $projectAssignment = \App\Models\ProjectEmployeeAssignment::where('project_id', $task->project_id)
                ->where('employee_id', $employee->id)
                ->first();

            if ($projectAssignment) {
                echo "  Project Assignment Status: {$projectAssignment->department_approval_status}\n";
                echo "  ✅ Should be included in results\n";
            } else {
                echo "  ❌ No project assignment found - should be excluded\n";
            }
        }
        echo "\n";
    }
} else {
    echo "Task not found!\n";
}
