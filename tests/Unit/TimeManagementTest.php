<?php

namespace Tests\Unit;

use App\Http\Controllers\API\TimeManagementController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TimeManagementTest extends TestCase
{
    /**
     * Test that required methods exist in TimeManagementController.
     */
    public function test_required_methods_exist(): void
    {
        $controller = new ReflectionClass(TimeManagementController::class);

        // Test that the new methods we added exist
        $requiredMethods = [
            'deleteProjectTask',
            'updateAssignedTask',
            'logAssignedTaskTimeSpent',
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue(
                $controller->hasMethod($methodName),
                "Method {$methodName} should exist in TimeManagementController"
            );
        }
    }

    /**
     * Test that existing methods still exist (not accidentally removed).
     */
    public function test_existing_methods_still_exist(): void
    {
        $controller = new ReflectionClass(TimeManagementController::class);

        $existingMethods = [
            'updatePersonalTask',
            'updateProjectTask',
            'updateAssignedTaskStatus',
            'logTimeSpent',
        ];

        foreach ($existingMethods as $methodName) {
            $this->assertTrue(
                $controller->hasMethod($methodName),
                "Method {$methodName} should exist in TimeManagementController"
            );
        }
    }
}
