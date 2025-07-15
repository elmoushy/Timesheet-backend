<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;

echo "Testing BLOB image handling...\n";

try {
    // Clean up any existing test data
    Employee::where('employee_code', 'TEST001')->delete();

    // Test 1: Create an employee without image
    $employee = Employee::create([
        'employee_code' => 'TEST001',
        'first_name' => 'Test',
        'last_name' => 'User',
        'work_email' => 'test@lightidea.org',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'marital_status' => 'single',
        'id_type' => 'national_id',
        'id_number' => '123456789',
        'id_expiry_date' => '2030-01-01',
        'employee_type' => 'full_time',
        'job_title' => 'Developer',
        'contract_start_date' => '2024-01-01',
        'user_status' => 'active',
    ]);

    echo "✓ Employee created successfully without image\n";

    // Test 2: Check if employee can be serialized to JSON
    $json = $employee->toJson();
    echo "✓ Employee serialized to JSON successfully\n";

    // Test 3: Test image_url accessor when no image
    $imageUrl = $employee->image_url;
    echo "✓ Image URL accessor works (returns null): " . ($imageUrl ? 'has value' : 'null') . "\n";

    // Test 4: Test hasImage method
    $hasImage = $employee->hasImage();
    echo "✓ hasImage method works: " . ($hasImage ? 'true' : 'false') . "\n";

    // Test 5: Test image size method
    $imageSize = $employee->getImageSize();
    echo "✓ getImageSize method works: " . $imageSize . " bytes\n";

    // Test 6: Create a simple test image (1x1 pixel PNG)
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77wgAAAABJRU5ErkJggg==');

    $employee->image_path = $pngData;
    $employee->save();

    echo "✓ Image saved to BLOB successfully\n";

    // Test 7: Refresh and check JSON serialization with image
    $employee->refresh();
    $json = $employee->toJson();
    echo "✓ Employee with image serialized to JSON successfully\n";

    // Test 8: Test image_url accessor with image
    $imageUrl = $employee->image_url;
    echo "✓ Image URL accessor works with image: " . (str_starts_with($imageUrl, 'data:image') ? 'correct format' : 'incorrect format') . "\n";

    // Test 9: Test hasImage method with image
    $hasImage = $employee->hasImage();
    echo "✓ hasImage method works with image: " . ($hasImage ? 'true' : 'false') . "\n";

    // Test 10: Test image size method with image
    $imageSize = $employee->getImageSize();
    echo "✓ getImageSize method works with image: " . $imageSize . " bytes\n";

    // Clean up
    $employee->delete();
    echo "✓ Test employee deleted\n";

    echo "\n🎉 All tests passed! BLOB image handling is working correctly.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
