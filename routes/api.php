<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TimesheetController;
use App\Http\Controllers\API\ProjectAssignEmployeeController;
use App\Http\Controllers\API\TimeManagementController;
use App\Http\Controllers\API\DepartmentManagerController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Employee routes - publicly accessible
Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::get('/search', [EmployeeController::class, 'search']); // New route for dropdown search
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::post('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
    Route::post('/bulk-delete', [EmployeeController::class, 'bulkDestroy']);
    Route::post('/{id}/upload-image', [EmployeeController::class, 'uploadImage']); // New route for image upload
});

// Department routes
Route::prefix('departments')->group(function () {
    Route::get('/', [DepartmentController::class, 'index']);
    Route::get('/search', [DepartmentController::class, 'search']); // New route for dropdown search
    Route::get('/{id}', [DepartmentController::class, 'show']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::put('/{id}', [DepartmentController::class, 'update']);
    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
    Route::post('/bulk-delete', [DepartmentController::class, 'bulkDestroy']);

    // Department manager routes
    Route::post('/{id}/managers', [DepartmentController::class, 'addManager']);
    Route::delete('/{departmentId}/managers/{employeeId}', [DepartmentController::class, 'removeManager']);

    // Employee assigned tasks route
    Route::get('/employees/{employeeId}/assigned-tasks', [DepartmentController::class, 'getEmployeeAssignedTasks']);
});

// Client routes
Route::prefix('clients')->group(function () {
    Route::get('/', [ClientController::class, 'index']);
    Route::get('/list', [ClientController::class, 'list']);
    Route::get('/{id}', [ClientController::class, 'show']);
    Route::post('/', [ClientController::class, 'store']);
    Route::put('/{id}', [ClientController::class, 'update']);
    Route::delete('/{id}', [ClientController::class, 'destroy']);
    Route::post('/bulk-delete', [ClientController::class, 'bulkDestroy']);
    Route::delete('/contact-numbers/{id}', [ClientController::class, 'destroyClientNumber']);
});

// Application routes
Route::prefix('applications')->group(function () {
    Route::get('/', [ApplicationController::class, 'index']);
    Route::get('/list', [ApplicationController::class, 'list']);
    Route::get('/search', [ApplicationController::class, 'search']);
    Route::get('/departments', [ApplicationController::class, 'departmentList']);
    Route::get('/{id}', [ApplicationController::class, 'show']);
    Route::post('/', [ApplicationController::class, 'store']);
    Route::put('/{id}', [ApplicationController::class, 'update']);
    Route::delete('/{id}', [ApplicationController::class, 'destroy']);
    Route::post('/bulk-delete', [ApplicationController::class, 'bulkDestroy']);
});

/**
 * Task Routes
 */
Route::prefix('tasks')->group(function () {
    Route::get('/', [TaskController::class, 'index']);
    Route::get('/list', [TaskController::class, 'list']);
    Route::get('/search', [TaskController::class, 'search']);
    Route::get('/departments', [TaskController::class, 'departmentList']);
    Route::get('/{id}', [TaskController::class, 'show']);
    Route::post('/', [TaskController::class, 'store']);
    Route::put('/{id}', [TaskController::class, 'update']);
    Route::delete('/{id}', [TaskController::class, 'destroy']);
    Route::post('/bulk-delete', [TaskController::class, 'bulkDestroy']);

    // New route to get tasks by project
    Route::get('/project/{projectId}', [TaskController::class, 'getTasksByProject']);

    // New route to get projects by department
    Route::get('/departments/{departmentId}/projects', [TaskController::class, 'getProjectsByDepartment']);
});

/**
 * Project Routes
 */
Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);

    // Specific named routes must come before parameter routes
    Route::get('/clientdropdown', [ClientController::class, 'list']);
    Route::get('/pending-assignment-requests', [ProjectController::class, 'getPendingRequests']);

    Route::delete('/contact-numbers/{id}', [ClientController::class, 'destroyClientNumber']);
    Route::get('/employeedropdown', [EmployeeController::class, 'search']);

    Route::get('/departments', [ApplicationController::class, 'departmentList']);
    Route::post('/bulk-delete', [ProjectController::class, 'bulkDestroy']);

    // Project employee assignment routes
    Route::post('/{id}/assign-employee', [ProjectController::class, 'assignEmployee'])->middleware('auth:sanctum');
    Route::put('/{projectId}/assignments/{assignmentId}', [ProjectController::class, 'updateAssignment']);
    Route::get('/{id}/assignments', [ProjectController::class, 'getAssignments']);
    Route::get('/{id}/employees-approval-status', [ProjectController::class, 'getEmployeesForProject']);

    // New route to get tasks for a project
    Route::get('/{id}/tasks', [ProjectController::class, 'getProjectTasks']);

    // Basic CRUD routes with parameters - these should come last
    Route::get('/{id}', [ProjectController::class, 'show']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);
});

// Authentication routes (public)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/change-password', [AuthController::class, 'changePassword']);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Auth verification
    Route::get('/check-token', [AuthController::class, 'checkToken']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Get projects assigned to authenticated user (no pagination)
    Route::get('/my-assigned-projects', [ProjectController::class, 'getMyAssignedProjects']);

    // Get tasks by project ID
    Route::get('/projects/{id}/tasks', [ProjectController::class, 'getProjectTasks']);

    // Timesheet routes - following REST convention
    Route::prefix('timesheets')->group(function () {
        // Get all timesheets (employee's own)
        Route::get('/', [TimesheetController::class, 'index']);

        // Get reopened timesheets
        Route::get('/reopened', [TimesheetController::class, 'reopenedList']);

        // Get timesheets pending approval
        Route::get('/pending', [TimesheetController::class, 'pendingApprovals']);

        // New role-specific approval endpoints
        Route::get('/pending-pm', [TimesheetController::class, 'pendingPM']);
        Route::get('/pending-dm', [TimesheetController::class, 'pendingDM']);
        Route::get('/pending-gm', [TimesheetController::class, 'pendingGM']);

        // Get workflow status for a timesheet
        Route::get('/{id}/workflow-status', [TimesheetController::class, 'workflowStatus']);

        // Get specific timesheet details
        Route::get('/{id}', [TimesheetController::class, 'show']);

        // Get workflow history for a timesheet
        Route::get('/{id}/flow', [TimesheetController::class, 'flowTrack']);

        // Create a new draft timesheet
        Route::post('/', [TimesheetController::class, 'createDraft']);

        // Update a draft timesheet
        Route::put('/{id}', [TimesheetController::class, 'updateDraft']);

        // Submit timesheet for approval
        Route::post('/{id}/submit', [TimesheetController::class, 'submit']);

        // Approve a timesheet
        Route::post('/{id}/approve', [TimesheetController::class, 'approve']);

        // Reject a timesheet
        Route::post('/{id}/reject', [TimesheetController::class, 'reject']);

        // Reopen a timesheet for editing
        Route::post('/{id}/reopen', [TimesheetController::class, 'reopen']);

        // Chat functionality
        Route::post('/{id}/chat', [TimesheetController::class, 'postChat']);
        Route::get('/{id}/chat', [TimesheetController::class, 'listChat']);
    });    // Project employee assignment approval routes
    Route::prefix('project-assignments')->group(function () {
        Route::get('/', [ProjectAssignEmployeeController::class, 'index']);
        Route::post('/{id}/approve', [ProjectAssignEmployeeController::class, 'approve']);
        Route::post('/{id}/reject', [ProjectAssignEmployeeController::class, 'reject']);
        Route::post('/{id}/resend', [ProjectAssignEmployeeController::class, 'resend']);
    });

    // Time Management routes for employees
    Route::prefix('time-management')->group(function () {
        // Dashboard and analytics
        Route::get('/dashboard', [TimeManagementController::class, 'getDashboard']);
        Route::get('/analytics', [TimeManagementController::class, 'getAnalytics']);
        Route::get('/important-tasks', [TimeManagementController::class, 'getImportantTasks']);
        Route::get('/project-assignments', [TimeManagementController::class, 'GetProject_Assignment']);

        // Personal tasks management
        Route::prefix('personal-tasks')->group(function () {
            Route::get('/', [TimeManagementController::class, 'getPersonalTasks']);
            Route::post('/', [TimeManagementController::class, 'storePersonalTask']);
            Route::put('/{id}', [TimeManagementController::class, 'updatePersonalTask']);
            Route::delete('/{id}', [TimeManagementController::class, 'deletePersonalTask']);
            Route::put('/{id}/status', [TimeManagementController::class, 'updatePersonalTaskStatus']);
        });

        // Project tasks (auto-generated from assignments)
        Route::prefix('project-tasks')->group(function () {
            Route::get('/', [TimeManagementController::class, 'getProjectTasks']);
            Route::post('/', [TimeManagementController::class, 'storeProjectTask']);
            Route::put('/{id}', [TimeManagementController::class, 'updateProjectTask']);
            Route::put('/{id}/status', [TimeManagementController::class, 'updateProjectTaskStatus']);
            Route::post('/{id}/time-spent', [TimeManagementController::class, 'logTimeSpent']);
        });

        // Assigned tasks from managers
        Route::prefix('assigned-tasks')->group(function () {
            Route::get('/', [TimeManagementController::class, 'getAssignedTasks']);
            Route::put('/{id}/status', [TimeManagementController::class, 'updateAssignedTaskStatus']);
            Route::post('/{id}/feedback', [TimeManagementController::class, 'submitTaskFeedback']);
        });
    });

    // Department Manager routes for task management
    Route::prefix('department-manager')->group(function () {
        // Core Dashboard API - 7 main endpoints as documented
        Route::get('/dashboard', [DepartmentManagerController::class, 'getDashboard']);
        Route::get('/available-tasks', [DepartmentManagerController::class, 'getAvailableTasks']);
        Route::get('/workload-heatmap', [DepartmentManagerController::class, 'getWorkloadHeatmap']);

        // Task assignment management
        Route::prefix('task-assignment')->group(function () {
            Route::get('/employees', [DepartmentManagerController::class, 'getManagedEmployees']);
            Route::get('/employees/simple', [DepartmentManagerController::class, 'getManagedEmployeesSimple']);
            Route::post('/assign', [DepartmentManagerController::class, 'assignTask']);
            Route::get('/assigned-tasks', [DepartmentManagerController::class, 'getAssignedTasks']);
            Route::put('/{id}/permissions', [DepartmentManagerController::class, 'updateTaskPermissions']);
            Route::put('/{id}/status', [DepartmentManagerController::class, 'updateTaskStatus']);
            Route::delete('/{id}', [DepartmentManagerController::class, 'deleteAssignedTask']);
        });

        // Employee assigned tasks
        Route::get('/employees/{employeeId}/assigned-tasks', [DepartmentController::class, 'getEmployeeAssignedTasks']);

        // Bulk operations
        Route::prefix('bulk-operations')->group(function () {
            Route::post('/tasks', [DepartmentManagerController::class, 'bulkTaskOperation']);
            Route::post('/assign-tasks', [DepartmentManagerController::class, 'bulkAssignTasks']);
            Route::post('/update-status', [DepartmentManagerController::class, 'bulkUpdateTaskStatus']);
            Route::post('/update-deadlines', [DepartmentManagerController::class, 'bulkUpdateDeadlines']);
            Route::get('/history', [DepartmentManagerController::class, 'getBulkOperationHistory']);
            Route::get('/{id}/status', [DepartmentManagerController::class, 'getBulkOperationStatus']);
        });

        // Employee workload management
        Route::prefix('workload')->group(function () {
            Route::get('/capacity/{employeeId}', [DepartmentManagerController::class, 'getEmployeeWorkloadCapacity']);
            Route::put('/capacity/{employeeId}', [DepartmentManagerController::class, 'updateWorkloadCapacity']);
            Route::get('/distribution', [DepartmentManagerController::class, 'getWorkloadDistribution']);
            Route::get('/recommendations', [DepartmentManagerController::class, 'getWorkloadRecommendations']);
        });

        // Analytics and reporting
        Route::prefix('analytics')->group(function () {
            Route::get('/progress-overview', [DepartmentManagerController::class, 'getProgressOverview']);
            Route::get('/department-performance', [DepartmentManagerController::class, 'getDepartmentPerformance']);
            Route::get('/employee-productivity/{employeeId}', [DepartmentManagerController::class, 'getEmployeeProductivity']);
            Route::get('/task-completion-trends', [DepartmentManagerController::class, 'getTaskCompletionTrends']);
            Route::get('/export/department-report', [DepartmentManagerController::class, 'exportDepartmentReport']);
        });
    });
});

