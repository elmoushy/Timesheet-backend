<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EmployeeWorkloadCapacity;

echo "=== Debugging Employee Workload Capacity Issue ===\n\n";

// Check current week start
$weekStart = now()->startOfWeek()->toDateString();
echo 'Current week start: '.$weekStart."\n";
echo 'Current date format: '.now()->startOfWeek()->format('Y-m-d H:i:s')."\n\n";

// Check all workload records for employee ID 4
echo "=== All Workload Records for Employee ID 4 ===\n";
$records = EmployeeWorkloadCapacity::where('employee_id', 4)->get();
echo 'Found '.$records->count()." records:\n";

foreach ($records as $record) {
    echo 'ID: '.$record->id.
         ', Employee ID: '.$record->employee_id.
         ', Week Start: '.$record->week_start_date.
         ', Hours: '.$record->current_planned_hours.
         ', Status: '.$record->workload_status."\n";
}
echo "\n";

// Check if there's already a record for current week
echo "=== Checking for Current Week Record ===\n";
$currentWeekRecord = EmployeeWorkloadCapacity::where('employee_id', 4)
    ->where('week_start_date', $weekStart)
    ->first();

if ($currentWeekRecord) {
    echo "FOUND existing record for current week:\n";
    echo 'ID: '.$currentWeekRecord->id.
         ', Week Start: '.$currentWeekRecord->week_start_date.
         ', Hours: '.$currentWeekRecord->current_planned_hours."\n";
} else {
    echo "NO existing record found for current week\n";
}
echo "\n";

// Test what firstOrCreate would do
echo "=== Testing firstOrCreate Logic ===\n";
try {
    $workload = EmployeeWorkloadCapacity::where('employee_id', 4)
        ->where('week_start_date', $weekStart)
        ->first();

    if ($workload) {
        echo "Record exists, would update existing record\n";
        echo 'Current hours: '.$workload->current_planned_hours."\n";
    } else {
        echo "No record exists, would create new record\n";
    }
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
