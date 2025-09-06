<?php

use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\DepartmentManagerController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\PageController;
use App\Http\Controllers\API\PageRolePermissionController;
use App\Http\Controllers\API\ProjectAssignEmployeeController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\Api\SsoController;
use App\Http\Controllers\API\SupportController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TestController;
use App\Http\Controllers\API\TimeManagementController;
use App\Http\Controllers\API\TimesheetController;
use App\Http\Controllers\API\UserRoleController;
use Illuminate\Support\Facades\Route;

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

// SSO Authentication routes - publicly accessible
Route::prefix('auth/sso')->group(function () {
    Route::post('/exchange', [SsoController::class, 'exchange']);
    Route::post('/refresh', [SsoController::class, 'refresh']);
    Route::post('/logout', [SsoController::class, 'logout']);
    Route::get('/me', [SsoController::class, 'me'])->middleware('jwt.auth');
});

// Employee routes - all require authentication
Route::prefix('employees')->middleware('jwt.auth')->group(function () {
    Route::get('/all', [EmployeeController::class, 'all']);
    Route::get('/', [EmployeeController::class, 'index']);
    Route::get('/search', [EmployeeController::class, 'search']); // New route for dropdown search
    Route::get('/{id}', [EmployeeController::class, 'show'])
        ->whereNumber('id');
    Route::post('/', [EmployeeController::class, 'store']);
    Route::post('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
    Route::post('/bulk-delete', [EmployeeController::class, 'bulkDestroy']);
    Route::post('/{id}/upload-image', [EmployeeController::class, 'uploadImage']); // New route for image upload
    Route::get('/{id}/image', [EmployeeController::class, 'getImage']); // Get employee image
    Route::delete('/{id}/image', [EmployeeController::class, 'deleteImage']); // Delete employee image
});

// Support routes - all require authentication
Route::prefix('support')->middleware('jwt.auth')->group(function () {
    Route::get('/', [SupportController::class, 'index']);
    Route::get('/all', [SupportController::class, 'all']);
    Route::get('/search', [SupportController::class, 'search']);
    Route::get('/{id}', [SupportController::class, 'show'])
        ->whereNumber('id');
    Route::get('/{id}/image', [SupportController::class, 'getImage']);
    Route::post('/', [SupportController::class, 'store']);
    Route::post('/{id}', [SupportController::class, 'update']);
    Route::delete('/{id}', [SupportController::class, 'destroy']);
    Route::post('/bulk-delete', [SupportController::class, 'bulkDestroy']);
    Route::post('/{id}/upload-image', [SupportController::class, 'uploadImage']);
    Route::delete('/{id}/image', [SupportController::class, 'deleteImage']);
});

// Department routes - all require authentication
Route::prefix('departments')->middleware('jwt.auth')->group(function () {
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

// Client routes - all require authentication
Route::prefix('clients')->middleware('jwt.auth')->group(function () {
    Route::get('/', [ClientController::class, 'index']);
    Route::get('/list', [ClientController::class, 'list']);
    Route::get('/{id}', [ClientController::class, 'show']);
    Route::post('/', [ClientController::class, 'store']);
    Route::put('/{id}', [ClientController::class, 'update']);
    Route::delete('/{id}', [ClientController::class, 'destroy']);
    Route::post('/bulk-delete', [ClientController::class, 'bulkDestroy']);
    Route::delete('/contact-numbers/{id}', [ClientController::class, 'destroyClientNumber']);
});

// Application routes - all require authentication
Route::prefix('applications')->middleware('jwt.auth')->group(function () {
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
 * Task Routes - all require authentication
 */
Route::prefix('tasks')->middleware('jwt.auth')->group(function () {
    Route::get('/', [TaskController::class, 'index']);
    Route::get('/list', [TaskController::class, 'list']);
    Route::get('/search', [TaskController::class, 'search']);
    Route::get('/departments', [TaskController::class, 'departmentList']);
    Route::get('/{id}', [TaskController::class, 'show']);

    // New route to get tasks by project
    Route::get('/project/{projectId}', [TaskController::class, 'getTasksByProject']);

    // New route to get projects by department
    Route::get('/departments/{departmentId}/projects', [TaskController::class, 'getProjectsByDepartment']);

    Route::post('/', [TaskController::class, 'store']);
    Route::put('/{id}', [TaskController::class, 'update']);
    Route::delete('/{id}', [TaskController::class, 'destroy']);
    Route::post('/bulk-delete', [TaskController::class, 'bulkDestroy']);
});

/**
 * Project Routes - all require authentication
 */
Route::prefix('projects')->middleware('jwt.auth')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);

    // Specific named routes must come before parameter routes
    Route::get('/clientdropdown', [ClientController::class, 'list']);
    Route::get('/pending-assignment-requests', [ProjectController::class, 'getPendingRequests']);

    Route::get('/employeedropdown', [EmployeeController::class, 'search']);
    Route::get('/departments', [ApplicationController::class, 'departmentList']);

    Route::get('/{id}/assignments', [ProjectController::class, 'getAssignments']);
    Route::get('/{id}/employees-approval-status', [ProjectController::class, 'getEmployeesForProject']);

    // New route to get tasks for a project
    Route::get('/{id}/tasks', [ProjectController::class, 'getProjectTasks']);

    // Basic read routes
    Route::get('/{id}', [ProjectController::class, 'show']);

    Route::delete('/contact-numbers/{id}', [ClientController::class, 'destroyClientNumber']);
    Route::post('/bulk-delete', [ProjectController::class, 'bulkDestroy']);

    // Project employee assignment routes
    Route::post('/{id}/assign-employee', [ProjectController::class, 'assignEmployee']);
    Route::put('/{projectId}/assignments/{assignmentId}', [ProjectController::class, 'updateAssignment']);

    // Basic CRUD routes
    Route::post('/', [ProjectController::class, 'store']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);
});

// Authentication routes (public)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/change-password', [AuthController::class, 'changePassword']);

// Protected routes - require JWT authentication
Route::middleware('jwt.auth')->group(function () {
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
            Route::delete('/{id}', [TimeManagementController::class, 'deleteProjectTask']);
            Route::put('/{id}/status', [TimeManagementController::class, 'updateProjectTaskStatus']);
            Route::post('/{id}/time-spent', [TimeManagementController::class, 'logTimeSpent']);
        });

        // Assigned tasks from managers
        Route::prefix('assigned-tasks')->group(function () {
            Route::get('/', [TimeManagementController::class, 'getAssignedTasks']);
            Route::put('/{id}', [TimeManagementController::class, 'updateAssignedTask']);
            Route::put('/{id}/status', [TimeManagementController::class, 'updateAssignedTaskStatus']);
            Route::post('/{id}/feedback', [TimeManagementController::class, 'submitTaskFeedback']);
            Route::post('/{id}/time-spent', [TimeManagementController::class, 'logAssignedTaskTimeSpent']);
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
            Route::post('/remove', [DepartmentManagerController::class, 'removeTaskAssignment']);
            Route::put('/update', [DepartmentManagerController::class, 'updateTaskAssignment']);
            Route::post('/details', [DepartmentManagerController::class, 'getTaskAssignmentDetails']);
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

// =====================================
// Permission Management Routes
// =====================================

// Pages routes - all require authentication
Route::prefix('pages')->middleware('jwt.auth')->group(function () {
    Route::get('/', [PageController::class, 'index']);
    Route::get('/{id}', [PageController::class, 'show']);
    Route::post('/', [PageController::class, 'store']);
    Route::put('/{id}', [PageController::class, 'update']);
    Route::delete('/{id}', [PageController::class, 'destroy']);
    Route::patch('/{id}/toggle-status', [PageController::class, 'toggleStatus']);
});

// Roles routes (enhanced) - all require authentication
Route::prefix('roles')->middleware('jwt.auth')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/{id}', [RoleController::class, 'show']);

    // Role relationships
    Route::get('/{id}/users', [RoleController::class, 'getUsers']);
    Route::get('/{id}/pages', [RoleController::class, 'getPages']);

    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{id}', [RoleController::class, 'update']);
    Route::delete('/{id}', [RoleController::class, 'destroy']);
    Route::patch('/{id}/toggle-status', [RoleController::class, 'toggleStatus']);
});

// User Roles routes - all require authentication
Route::prefix('user-roles')->middleware('jwt.auth')->group(function () {
    Route::get('/', [UserRoleController::class, 'index']);
    Route::get('/{id}', [UserRoleController::class, 'show']);

    // User and role specific endpoints
    Route::get('/users/{userId}', [UserRoleController::class, 'getUserRoles']);
    Route::get('/roles/{roleId}', [UserRoleController::class, 'getRoleUsers']);

    Route::post('/', [UserRoleController::class, 'store']);
    Route::put('/{id}', [UserRoleController::class, 'update']);
    Route::delete('/{id}', [UserRoleController::class, 'destroy']);
    Route::patch('/{id}/toggle-status', [UserRoleController::class, 'toggleStatus']);

    // Bulk operations
    Route::post('/bulk-assign', [UserRoleController::class, 'bulkAssignToUser']);
});

// Page Role Permissions routes
Route::prefix('page-role-permissions')->middleware('jwt.auth')->group(function () {
    Route::get('/', [PageRolePermissionController::class, 'index']);
    Route::get('/{id}', [PageRolePermissionController::class, 'show']);
    Route::post('/', [PageRolePermissionController::class, 'store']);
    Route::put('/{id}', [PageRolePermissionController::class, 'update']);
    Route::delete('/{id}', [PageRolePermissionController::class, 'destroy']);
    Route::patch('/{id}/toggle-status', [PageRolePermissionController::class, 'toggleStatus']);

    // Bulk operations
    Route::post('/bulk-assign-pages', [PageRolePermissionController::class, 'bulkAssignPagesToRole']);

    // Page and role specific endpoints
    Route::get('/pages/{pageId}', [PageRolePermissionController::class, 'getPagePermissions']);
    Route::get('/roles/{roleId}', [PageRolePermissionController::class, 'getRolePermissions']);

    // Permission matrix
    Route::get('/matrix', [PageRolePermissionController::class, 'getPermissionMatrix']);
});

// Bulk operations - require authentication
Route::prefix('page-role-permissions')->middleware('jwt.auth')->group(function () {
    Route::post('/bulk-store', [PageRolePermissionController::class, 'bulkStore']);
    Route::post('/bulk-delete', [PageRolePermissionController::class, 'bulkDelete']);
});

// =====================================
// Test Routes for Debugging - require authentication
// =====================================
Route::prefix('test')->middleware('jwt.auth')->group(function () {
    Route::get('/utf8', [TestController::class, 'testUtf8']);
    Route::get('/binary', [TestController::class, 'testBinary']);
    Route::get('/memory', [TestController::class, 'testMemory']);
});
