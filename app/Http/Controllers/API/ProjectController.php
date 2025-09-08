<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectEmployeeAssignment;
use App\Models\Task; // Add Employee model import
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ProjectController extends Controller
{
    /* ─────────────────────  Helpers  ───────────────────── */
    private function ok(string $msg, $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => $data], $code);
    }

    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => []], $code);
    }

    /* ─────────────────────  Core rules  ───────────────────── */
    private function projectRules(int $id = 0): array
    {
        return [
            'client_id' => 'required|integer|exists:xxx_clients,id',
            'project_name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('xxx_projects')->ignore($id),
            ],
            'department_id' => 'required|integer|exists:xxx_departments,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_manager_id' => 'nullable|integer|exists:xxx_employees,id',
            'notes' => 'nullable|string',

            /* ==== CHILD COLLECTIONS ==== */
            'products' => 'sometimes|array',
            'products.*' => 'required_with:products|integer|exists:xxx_products,id',

            // Updated validation for managers array with improved rules
            'managers' => 'sometimes|array',
            'managers.*.employee_id' => [
                'required',
                'integer',
                'min:1', // Ensure employee_id is at least 1
                'exists:xxx_employees,id',
            ],
            'managers.*.role' => 'required|string|in:lead,assistant,coordinator',
            'managers.*.start_date' => 'required|date',
            'managers.*.end_date' => 'nullable|date|after_or_equal:managers.*.start_date',

            'contact_numbers' => 'nullable|array',
            'contact_numbers.*.name' => 'required_with:contact_numbers|string|max:100',
            'contact_numbers.*.number' => 'required_with:contact_numbers|string|max:50',
            'contact_numbers.*.type' => 'nullable|string|in:client,oracle,private,other',
            'contact_numbers.*.is_primary' => 'boolean',
        ];
    }

    /* ─────────────────────  Index + Show  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        // Only load the minimal relationships needed as per API requirements
        $query = Project::with(['client:id,name,alias', 'department:id,name']);

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('client', function ($clientQuery) use ($searchTerm) {
                    $clientQuery->where('name', 'LIKE', $searchTerm);
                })
                    ->orWhere('project_name', 'LIKE', $searchTerm)
                    ->orWhere('notes', 'LIKE', $searchTerm);
            });
        }

        // Apply client filter if provided
        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        // Apply department filter if provided
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Apply date filters if provided and not empty
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->input('end_date'));
        }

        $projects = $query->paginate($request->input('per_page', 10));

        // Transform using ProjectResource collection
        $transformedData = $projects->toArray();
        $transformedData['data'] = ProjectResource::collection($projects->items())->resolve();

        return $this->ok('Projects fetched successfully', $transformedData);
    }

    public function show(int $id): JsonResponse
    {
        // Load only the needed relationships with specific fields
        $project = Project::with([
            'client:id,name,alias',
            'department:id,name',
            'manager:id,first_name,last_name',
            'managers:id,first_name,last_name',
            'products:id',
            'contactNumbers',
        ])->find($id);

        if (!$project) {
            return $this->fail('Project not found', 404);
        }

        return $this->ok('Project fetched successfully', new ProjectResource($project));
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        // Clean and prepare request data
        $requestData = $request->all();

        // Handle special cases for zero values and empty strings
        if (isset($requestData['project_manager_id']) && $requestData['project_manager_id'] == 0) {
            $requestData['project_manager_id'] = null;
        }

        if (isset($requestData['client_id']) && $requestData['client_id'] == 0) {
            return $this->fail('Please select a valid client for the project.', 422);
        }

        if (isset($requestData['department_id']) && $requestData['department_id'] == 0) {
            return $this->fail('Please select a valid department for the project.', 422);
        }

        if (isset($requestData['end_date']) && $requestData['end_date'] === '') {
            $requestData['end_date'] = null;
        }

        // Handle contact_numbers - remove client_id processing since project contact numbers are independent
        // Remove this block that was setting client_id for contact numbers
        // if (isset($requestData['contact_numbers']) && is_array($requestData['contact_numbers'])) {
        //     foreach ($requestData['contact_numbers'] as $index => $contactNumber) {
        //         if (isset($contactNumber['client_id']) && $contactNumber['client_id'] == 0) {
        //             // Use the project's client_id instead
        //             $requestData['contact_numbers'][$index]['client_id'] = $requestData['client_id'];
        //         }
        //     }
        // }

        $v = Validator::make(
            $requestData,
            $this->projectRules(),
            [
                'client_id.required' => 'Please select a client for the project.',
                'client_id.exists' => 'The selected client does not exist.',
                'department_id.required' => 'Please select a department for the project.',
                'department_id.exists' => 'The selected department does not exist.',
                'project_name.required' => 'Project name is required.',
                'project_name.unique' => 'A project with this name already exists.',
                'start_date.required' => 'Project start date is required.',
                'end_date.after_or_equal' => 'Project end date must be after or equal to start date.',
                'managers.*.employee_id.min' => 'Please select a valid employee for the project manager.',
                'managers.*.employee_id.exists' => 'The selected employee ID does not exist in our records.',
            ]
        );

        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        // Enhanced validation for managers array
        if ($request->has('managers') && is_array($request->managers)) {
            if (empty($request->managers)) {
                return $this->fail('Managers array cannot be empty', 422);
            }

            $missingFields = [];
            foreach ($request->managers as $index => $manager) {
                $required = ['employee_id', 'role', 'start_date'];
                $missing = [];

                foreach ($required as $field) {
                    if (! isset($manager[$field])) {
                        $missing[] = $field;
                    }
                }

                if (! empty($missing)) {
                    $missingFields[] = "Manager at index {$index} is missing: ".implode(', ', $missing);
                }
            }

            if (! empty($missingFields)) {
                return $this->fail(implode('. ', $missingFields), 422);
            }
        }

        DB::beginTransaction();
        try {
            // Prepare project data using the processed request data
            $projectData = collect($requestData)->only([
                'client_id', 'project_name', 'department_id',
                'start_date', 'end_date', 'project_manager_id', 'notes',
            ])->toArray();

            // Create the project with basic data
            $project = Project::create($projectData);

            // Sync products if provided
            if (isset($requestData['products'])) {
                $project->products()->sync($requestData['products']);
            }

            // Add project managers if provided
            if (isset($requestData['managers'])) {
                foreach ($requestData['managers'] as $manager) {
                    $project->managers()->attach($manager['employee_id'], [
                        'role' => $manager['role'],
                        'start_date' => $manager['start_date'],
                        'end_date' => $manager['end_date'] ?? null,
                    ]);
                }
            }

            // Add contact numbers if provided - these are project-specific contact numbers
            if (isset($requestData['contact_numbers'])) {
                foreach ($requestData['contact_numbers'] as $contactNumber) {
                    $project->contactNumbers()->create([
                        'client_id' => $project->client_id, // Keep client_id as required by database constraint
                        'project_id' => $project->id, // Set project_id to make it project-specific
                        'name' => $contactNumber['name'],
                        'number' => $contactNumber['number'],
                        'type' => $contactNumber['type'] ?? 'client',
                        'is_primary' => $contactNumber['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();

            return $this->ok('Project created successfully',
                $project->load(['client', 'department', 'managers', 'products', 'contactNumbers']), 201);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error creating project: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) {
            return $this->fail('Project not found', 404);
        }

        $v = Validator::make(
            $request->all(),
            $this->projectRules($id),
            [
                'managers.*.employee_id.min' => 'Please select a valid employee for the project manager.',
                'managers.*.employee_id.exists' => 'The selected employee ID does not exist in our records.',
            ]
        );

        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        // Enhanced validation for managers array
        if ($request->has('managers') && is_array($request->managers)) {
            if (empty($request->managers)) {
                return $this->fail('Managers array cannot be empty', 422);
            }

            $missingFields = [];
            foreach ($request->managers as $index => $manager) {
                $required = ['employee_id', 'role', 'start_date'];
                $missing = [];

                foreach ($required as $field) {
                    if (! isset($manager[$field])) {
                        $missing[] = $field;
                    }
                }

                if (! empty($missing)) {
                    $missingFields[] = "Manager at index {$index} is missing: ".implode(', ', $missing);
                }
            }

            if (! empty($missingFields)) {
                return $this->fail(implode('. ', $missingFields), 422);
            }
        }

        DB::beginTransaction();
        try {
            // Update basic project information
            $project->fill($request->only([
                'client_id', 'project_name', 'department_id',
                'start_date', 'end_date', 'notes',
            ]));
            $project->save();

            // Sync products if provided
            if ($request->has('products')) {
                $project->products()->sync($request->input('products'));
            }

            // Update project managers if provided
            if ($request->has('managers')) {
                // First, remove all existing managers
                $project->managers()->detach();

                // Then add the new ones
                foreach ($request->input('managers') as $manager) {
                    $project->managers()->attach($manager['employee_id'], [
                        'role' => $manager['role'],
                        'start_date' => $manager['start_date'],
                        'end_date' => $manager['end_date'] ?? null,
                    ]);
                }
            }

            // Update contact numbers if provided - these are project-specific contact numbers
            if ($request->has('contact_numbers')) {
                // Delete existing project contact numbers (only those with project_id set)
                $project->contactNumbers()->whereNotNull('project_id')->delete();

                // Add new contact numbers for the project
                foreach ($request->input('contact_numbers') as $contactNumber) {
                    $project->contactNumbers()->create([
                        'client_id' => $project->client_id, // Keep client_id as required by database constraint
                        'project_id' => $project->id, // Set project_id to make it project-specific
                        'name' => $contactNumber['name'],
                        'number' => $contactNumber['number'],
                        'type' => $contactNumber['type'] ?? 'client',
                        'is_primary' => $contactNumber['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();

            return $this->ok('Project updated successfully',
                $project->load(['client', 'department', 'managers', 'products', 'contactNumbers']));
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error updating project: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) {
            return $this->fail('Project not found', 404);
        }

        try {
            $project->delete(); // Will cascade delete related records as per DB constraints

            return $this->ok('Project deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting project: '.$e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non-empty array', 422);
        }

        try {
            $deleted = Project::whereIn('id', $ids)->delete();

            return $this->ok($deleted ? "$deleted project(s) deleted successfully" : 'No projects were deleted');
        } catch (Throwable $e) {
            return $this->fail('Error deleting projects: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Search for Dropdown  ───────────────────── */
    public function clientdropdown(Request $request): JsonResponse
    {
        $query = client::query()
            ->select('id', 'name');
        if ($request->filled('term')) {
            $searchTerm = '%'.$request->input('term').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'LIKE', $searchTerm)
                    ->orWhereHas('client', function ($clientQuery) use ($searchTerm) {
                        $clientQuery->where('name', 'LIKE', $searchTerm);
                    });
            });
        }

        // Get the results - limit to reasonable amount for dropdown
        $results = $query->get();

        // Format results for dropdown with display text and value
        $formattedResults = $results->map(function ($project) {
            return [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'client_name' => $project->client->name ?? 'Unknown Client',
                'start_date' => $project->start_date->format('Y-m-d'),
                'end_date' => $project->end_date ? $project->end_date->format('Y-m-d') : 'Ongoing',
                'display_text' => $project->project_name.' - '.($project->client->name ?? 'Unknown Client'),
            ];
        });

        return $this->ok('Project search results', $formattedResults);
    }

    /* ─────────────────────  Project Employee Assignment  ───────────────────── */
    public function assignEmployee(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) {
            return $this->fail('Project not found', 404);
        }

        $v = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:xxx_employees,id',
            'notes' => 'nullable|string',
        ]);

        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            // prevent duplicate
            $existingAssignment = ProjectEmployeeAssignment::where('project_id', $id)
                ->where('employee_id', $request->employee_id)
                ->first();
            if ($existingAssignment) {
                return $this->fail('Employee already assigned to this project', 422);
            }

            // Check if user is authenticated
            $user = $request->user();
            if (! $user) {
                return $this->fail('User not authenticated', 401);
            }
            $requestedBy = $user->id;

            // get employee & compare departments
            $employee = Employee::find($request->employee_id);
            $sameAsDept = $employee->department_id === $project->department_id;

            $assignment = new ProjectEmployeeAssignment([
                'project_id' => $id,
                'employee_id' => $request->employee_id,
                'department_approval_status' => $sameAsDept ? 'approved' : 'pending',
                'requested_by' => $requestedBy,
                'requested_at' => now(),
                'notes' => $request->notes,
            ]);

            if ($sameAsDept) {
                $assignment->response_at = now();
                $assignment->approved_by = $requestedBy;
            }

            $assignment->save();

            return $this->ok(
                'Employee assignment '.($sameAsDept ? 'approved' : 'requested').' successfully',
                $assignment
            );
        } catch (Throwable $e) {
            return $this->fail('Error assigning employee: '.$e->getMessage(), 500);
        }
    }

    public function updateAssignment(Request $request, int $projectId, int $assignmentId): JsonResponse
    {
        $assignment = ProjectEmployeeAssignment::where('project_id', $projectId)
            ->where('id', $assignmentId)
            ->first();

        if (! $assignment) {
            return $this->fail('Assignment not found', 404);
        }

        $v = Validator::make($request->all(), [
            'department_approval_status' => 'required|in:approved,rejected',
            'approved_by' => 'required|integer|exists:xxx_employees,id',
            'notes' => 'nullable|string',
        ]);

        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            if ($request->department_approval_status === 'approved') {
                $assignment->approve($request->approved_by, $request->notes);
            } else {
                $assignment->reject($request->approved_by, $request->notes);
            }

            return $this->ok('Assignment updated successfully', $assignment->fresh());
        } catch (Throwable $e) {
            return $this->fail('Error updating assignment: '.$e->getMessage(), 500);
        }
    }

    public function getAssignments(int $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) {
            return $this->fail('Project not found', 404);
        }

        $assignments = ProjectEmployeeAssignment::where('project_id', $id)
            ->with(['employee'])
            ->get();

        return $this->ok('Project assignments fetched successfully', $assignments);
    }

    /**
     * Get all pending assignment requests for department managers
     */
    public function getPendingRequests(Request $request): JsonResponse
    {
        try {
            // Fix: Use id directly
            $employeeId = $request->user()->id;

            // Get departments where this employee is a manager
            $managedDepartmentIds = \App\Models\DepartmentManager::where('employee_id', $employeeId)
                ->pluck('department_id');

            if ($managedDepartmentIds->isEmpty()) {
                return $this->ok('No departments managed by you', []);
            }

            // Get pending assignments for employees in these departments
            $pendingAssignments = ProjectEmployeeAssignment::where('department_approval_status', 'pending')
                ->whereHas('employee', function ($query) use ($managedDepartmentIds) {
                    $query->whereIn('department_id', $managedDepartmentIds);
                })
                ->with(['employee', 'employee.department', 'project', 'requester'])
                ->get();

            return $this->ok('Pending assignment requests retrieved successfully', $pendingAssignments);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving pending requests: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all employees with their actual project assignment approval status
     */
    public function getEmployeesForProject(int $id): JsonResponse
    {
        try {
            $project = Project::find($id);
            if (! $project) {
                return $this->fail('Project not found', 404);
            }

            // Get all active employees
            $employees = \App\Models\Employee::where('user_status', 'active')
                ->with(['department', 'projectAssignments' => function ($query) use ($id) {
                    $query->where('project_id', $id);
                }])
                ->get();

            // Map employees with their project assignment status
            $employeesWithApprovalStatus = $employees->map(function ($employee) use ($project) {
                $assignment = $employee->projectAssignments->first();

                if ($assignment) {
                    // Employee is assigned to this project
                    return [
                        'id' => $employee->id,
                        'name' => $employee->getFullNameAttribute(),
                        'email' => $employee->work_email,
                        'department_id' => $employee->department_id,
                        'department_name' => $employee->department->name ?? 'Unknown',
                        'is_assigned' => true,
                        'approval_status' => $assignment->department_approval_status,
                        'assigned_at' => $assignment->created_at,
                        'approved_at' => $assignment->response_at,
                    ];
                } else {
                    // Employee is not assigned to this project
                    $needsApproval = $employee->department_id !== $project->department_id;

                    return [
                        'id' => $employee->id,
                        'name' => $employee->getFullNameAttribute(),
                        'email' => $employee->work_email,
                        'department_id' => $employee->department_id,
                        'department_name' => $employee->department->name ?? 'Unknown',
                        'is_assigned' => false,
                        'approval_status' => $needsApproval ? 'need_approval' : 'auto_approved',
                        'assigned_at' => null,
                        'approved_at' => null,
                    ];
                }
            });

            return $this->ok('Employees with approval status retrieved successfully', $employeesWithApprovalStatus);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving employees: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all tasks for a specific project
     * Returns only task ID and name
     */
    public function getProjectTasks(int $id): JsonResponse
    {
        try {
            $project = Project::find($id);

            if (! $project) {
                return $this->fail('Project not found', 404);
            }

            $tasks = Task::where('project_id', $id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return $this->ok('Project tasks retrieved successfully', $tasks);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving project tasks: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all projects assigned to the authenticated user
     * Returns only project ID and name
     */
    public function getMyAssignedProjects(Request $request): JsonResponse
    {
        try {
            // Get the authenticated employee
            $employee = $request->user();
            if (! $employee) {
                return $this->fail('Unauthorized access', 401);
            }

            // Find projects where the employee is assigned, but only return id and project_name
            $assignedProjects = Project::whereHas('employeeAssignments', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->where('department_approval_status', 'approved');
            })
                ->select('id', 'project_name')
                ->orderBy('project_name')
                ->get();

            return $this->ok('Assigned projects retrieved successfully', $assignedProjects);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving assigned projects: '.$e->getMessage(), 500);
        }
    }
}
