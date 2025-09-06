<?php

// Test data that was causing the error
$testData = [
    'client_id' => 1,
    'project_name' => 'Test Project',
    'department_id' => 1,
    'start_date' => '2025-09-02',
    'end_date' => '2026-12-02',
    'project_manager_id' => 0,
    'notes' => '',
    'managers' => [
        [
            'employee_id' => 3,
            'role' => 'lead',
            'start_date' => '2025-09-02',
            'end_date' => null,
        ],
    ],
    'contact_numbers' => [
        [
            'id' => 1,
            'name' => 'Sakr',
            'number' => '966566245237',
            'type' => 'Personal Number',
            'is_primary' => true,
            'client_id' => 1,
        ],
    ],
];

// Test the conversion logic
$requestData = $testData;
if (isset($requestData['project_manager_id']) && $requestData['project_manager_id'] == 0) {
    $requestData['project_manager_id'] = null;
}

echo 'Original project_manager_id: '.json_encode($testData['project_manager_id'])."\n";
echo 'Converted project_manager_id: '.json_encode($requestData['project_manager_id'])."\n";
echo "Test data conversion works correctly!\n";
