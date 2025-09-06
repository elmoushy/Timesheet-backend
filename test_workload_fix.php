<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EmployeeWorkloadCapacity;

echo "=== Testing Fixed updateEmployeeWorkload Logic ===\n\n";

// Simulate the fixed logic
$employeeId = 4;
$hours = -4; // Removing 4 hours (like in the original error)
$weekStart = now()->startOfWeek();

echo 'Employee ID: '.$employeeId."\n";
echo 'Hours to adjust: '.$hours."\n";
echo 'Week start: '.$weekStart->toDateString()."\n\n";

// Show current state
echo "=== Current State ===\n";
$currentRecord = EmployeeWorkloadCapacity::where('employee_id', $employeeId)
    ->whereDate('week_start_date', $weekStart->toDateString())
    ->first();

if ($currentRecord) {
    echo "Found existing record:\n";
    echo 'Current planned hours: '.$currentRecord->current_planned_hours."\n";
    echo 'Current status: '.$currentRecord->workload_status."\n\n";
} else {
    echo "No existing record found\n\n";
}

// Test the logic without actually saving
echo "=== Testing Logic (without saving) ===\n";

if ($currentRecord) {
    $newPlannedHours = $currentRecord->current_planned_hours + $hours;
    $finalHours = max(0, $newPlannedHours);
    $percentage = ($finalHours / 40) * 100;

    echo 'New planned hours would be: '.$finalHours."\n";
    echo 'New percentage would be: '.round($percentage, 2)."%\n";

    if ($percentage < 70) {
        $status = 'under_utilized';
    } elseif ($percentage <= 100) {
        $status = 'optimal';
    } elseif ($percentage <= 120) {
        $status = 'over_loaded';
    } else {
        $status = 'critical';
    }

    echo 'New status would be: '.$status."\n";
} else {
    echo "Would create new record with 0 hours\n";
}
