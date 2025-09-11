<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimesheetResource;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentManager;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\TimesheetChat;
use App\Models\TimesheetRow;
use App\Models\TimesheetWorkflowHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TimesheetController extends Controller
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

    /* ─────────────────────  Validation Rules  ───────────────────── */
    private function timesheetRules(): array
    {
        return [
            'period_start' => 'required|date|date_format:Y-m-d',
            'period_end' => 'required|date|date_format:Y-m-d|after_or_equal:period_start',
            'rows' => 'required|array|min:1',
            'rows.*.project_id' => 'required|integer|exists:xxx_projects,id',
            'rows.*.task_id' => 'required|integer|exists:xxx_tasks,id',
            'rows.*.hours_monday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_tuesday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_wednesday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_thursday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_friday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_saturday' => 'required|numeric|min:0|max:24',
            'rows.*.hours_sunday' => 'required|numeric|min:0|max:24',
            'rows.*.achievement_note' => 'nullable|string|max:500',
        ];
    }

    /* ─────────────────────  Create Draft Timesheet  ───────────────────── */
    public function createDraft(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), $this->timesheetRules());
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        DB::beginTransaction();
        try {
            // Check if a timesheet already exists for this period
            $existingTimesheet = Timesheet::where('employee_id', $employee->id)
                ->where('period_start', $periodStart)
                ->first();

            // If exists and not in draft or reopened status, prevent modification
            if ($existingTimesheet && ! in_array($existingTimesheet->overall_status, ['draft', 'reopened'])) {
                return $this->fail('Cannot modify a timesheet that has already been submitted', 422);
            }

            // Create or update the timesheet
            $timesheet = $existingTimesheet ?: new Timesheet;
            $timesheet->employee_id = $employee->id;
            $timesheet->period_start = $periodStart;
            $timesheet->period_end = $periodEnd;
            $timesheet->overall_status = $existingTimesheet && $existingTimesheet->overall_status === 'reopened'
                ? 'reopened' : 'draft';
            $timesheet->save();

            // Delete existing rows if any
            if ($existingTimesheet) {
                TimesheetRow::where('timesheet_id', $timesheet->id)->delete();
            }

            // Create timesheet rows
            foreach ($request->input('rows') as $rowData) {
                $row = new TimesheetRow([
                    'timesheet_id' => $timesheet->id,
                    'project_id' => $rowData['project_id'],
                    'task_id' => $rowData['task_id'],
                    'hours_monday' => $rowData['hours_monday'],
                    'hours_tuesday' => $rowData['hours_tuesday'],
                    'hours_wednesday' => $rowData['hours_wednesday'],
                    'hours_thursday' => $rowData['hours_thursday'],
                    'hours_friday' => $rowData['hours_friday'],
                    'hours_saturday' => $rowData['hours_saturday'],
                    'hours_sunday' => $rowData['hours_sunday'],
                    'achievement_note' => $rowData['achievement_note'] ?? null,
                ]);
                $row->save();
            }

            DB::commit();

            // Return the timesheet with its rows
            $timesheet->load(['rows.project', 'rows.task']);

            return $this->ok(
                $existingTimesheet ? 'Timesheet updated successfully' : 'Timesheet draft created successfully',
                $timesheet,
                $existingTimesheet ? 200 : 201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error creating timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update Draft Timesheet  ───────────────────── */
    public function updateDraft(Request $request, int $id): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), $this->timesheetRules());
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        DB::beginTransaction();
        try {
            // Find the timesheet to update
            $timesheet = Timesheet::where('id', $id)
                ->where('employee_id', $employee->id)
                ->first();

            if (! $timesheet) {
                return $this->fail('Timesheet not found or you do not have permission to update it', 404);
            }

            // Check if timesheet is in a state that can be updated
            if (! in_array($timesheet->overall_status, ['draft', 'reopened'])) {
                return $this->fail('Cannot modify a timesheet that is not in draft or reopened status', 422);
            }

            // Update timesheet details
            $timesheet->period_start = $request->input('period_start');
            $timesheet->period_end = $request->input('period_end');
            $timesheet->save();

            // Delete existing rows
            TimesheetRow::where('timesheet_id', $timesheet->id)->delete();

            // Create new timesheet rows
            foreach ($request->input('rows') as $rowData) {
                $row = new TimesheetRow([
                    'timesheet_id' => $timesheet->id,
                    'project_id' => $rowData['project_id'],
                    'task_id' => $rowData['task_id'],
                    'hours_monday' => $rowData['hours_monday'],
                    'hours_tuesday' => $rowData['hours_tuesday'],
                    'hours_wednesday' => $rowData['hours_wednesday'],
                    'hours_thursday' => $rowData['hours_thursday'],
                    'hours_friday' => $rowData['hours_friday'],
                    'hours_saturday' => $rowData['hours_saturday'],
                    'hours_sunday' => $rowData['hours_sunday'],
                    'achievement_note' => $rowData['achievement_note'] ?? null,
                ]);
                $row->save();
            }

            DB::commit();

            // Return the updated timesheet with its rows
            $timesheet->load(['rows.project', 'rows.task']);

            return $this->ok('Timesheet updated successfully', $timesheet);

        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error updating timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Submit Timesheet  ───────────────────── */
    public function submit(Request $request, int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        DB::beginTransaction();
        try {
            // Lock the timesheet row to prevent concurrent submissions
            $timesheet = Timesheet::with('rows')
                ->where('id', $id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate() // added locking to prevent race conditions
                ->first();

            if (! $timesheet) {
                return $this->fail('Timesheet not found or you do not have permission to access it', 404);
            }

            // Check if timesheet can be submitted
            if (! in_array($timesheet->overall_status, ['draft', 'reopened'])) {
                return $this->fail('Timesheet cannot be submitted in its current state', 422);
            }

            // Ensure timesheet has rows
            if ($timesheet->rows->isEmpty()) {
                return $this->fail('Timesheet must have at least one row before submission', 422);
            }

            // Check if this is a resubmission (was reopened before)
            $isResubmission = ($timesheet->overall_status === 'reopened');

            // Delete any existing approval records if this is a resubmission
            if ($isResubmission) {
                TimesheetApproval::where('timesheet_id', $timesheet->id)->delete();
            }

            // Update timesheet status
            $timesheet->overall_status = 'in_review';
            $timesheet->submitted_at = now();
            $timesheet->save();

            // Record in workflow history with appropriate action
            // Changed to 'submitted' to conform with allowed DB values
            $actionType = 'submitted';
            $this->addWorkflowHistory($timesheet->id, 'employee', $actionType, null, $employee->id);

            // Find project managers for each project in the timesheet rows
            $projectIds = $timesheet->rows->pluck('project_id')->unique();

            // Get all project managers for these projects
            $projectManagers = ProjectManager::whereIn('project_id', $projectIds)
                ->get()
                ->unique('employee_id');

            // Create approval records for project managers
            foreach ($projectManagers as $pm) {
                TimesheetApproval::create([
                    'timesheet_id' => $timesheet->id,
                    'approver_id' => $pm->employee_id,
                    'approver_role' => 'pm',
                    'status' => 'pending',
                ]);
            }

            // If no project managers, auto-proceed to department manager
            if ($projectManagers->isEmpty()) {
                // Get employee's department manager
                $employeeDept = Employee::with('department')->find($employee->id);

                if ($employeeDept && $employeeDept->department) {
                    $deptManagers = DepartmentManager::where('department_id', $employeeDept->department_id)
                        ->get();

                    foreach ($deptManagers as $dm) {
                        TimesheetApproval::create([
                            'timesheet_id' => $timesheet->id,
                            'approver_id' => $dm->employee_id,
                            'approver_role' => 'dm',
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            DB::commit();

            // Return the updated timesheet
            $timesheet->load(['rows.project', 'rows.task', 'approvals.approver']);

            $message = $isResubmission ? 'Timesheet resubmitted successfully' : 'Timesheet submitted successfully';

            return $this->ok($message, $timesheet);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error submitting timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Index - List Timesheets  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1|max:1000',
            'per_page' => 'integer|min:10|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,submitted,approved,rejected,reopened,in_review',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'employee_id' => 'nullable|integer|exists:xxx_employees,id',
            'department_id' => 'nullable|integer|exists:xxx_departments,id',
            'project_id' => 'nullable|integer|exists:xxx_projects,id',
            'client_id' => 'nullable|integer|exists:xxx_clients,id',
            'sort_by' => 'nullable|string|in:period_start,period_end,total_hours,overall_status,employee_name,created_at,updated_at,submitted_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'is_reopen_tab' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            // Check if user has permission to view all timesheets
            $canViewAll = $this->isManagerOrAdmin($employee);
            $isDepartmentManager = $this->isDepartmentManager($employee);

            // Start building the query with optimized relationships
            $query = Timesheet::with([
                'employee:id,first_name,last_name,employee_code,work_email,department_id',
                'employee.department:id,name',
                'rows.project:id,project_name,department_id,client_id',
                'rows.project.client:id,name',
                'rows.task:id,name,task_type',
                'approvals:id,timesheet_id,approver_role,status,acted_at',
                'chats:id,timesheet_id'
            ]);

            // Apply user-based filtering
            if (!$canViewAll) {
                if ($isDepartmentManager) {
                    // Department managers can see timesheets from their managed departments
                    $managedDepartmentIds = DepartmentManager::where('employee_id', $employee->id)
                        ->where('is_active', true)
                        ->pluck('department_id');

                    $query->whereHas('employee', function ($q) use ($managedDepartmentIds, $employee) {
                        $q->where(function ($subQ) use ($managedDepartmentIds, $employee) {
                            $subQ->whereIn('department_id', $managedDepartmentIds)
                                ->orWhere('id', $employee->id); // Include own timesheets
                        });
                    });
                } else {
                    // Regular employees can only see their own timesheets
                    $query->where('employee_id', $employee->id);
                }
            }

            // Apply explicit filters if user has permission
            if ($request->filled('employee_id') && $canViewAll) {
                $query->where('employee_id', $request->input('employee_id'));
            }

            if ($request->filled('department_id') && ($canViewAll || $isDepartmentManager)) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->input('department_id'));
                });
            }

            // Apply search filter - multi-field search
            if ($request->filled('search')) {
                $searchTerm = '%' . trim($request->input('search')) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereHas('employee', function ($employeeQ) use ($searchTerm) {
                        $employeeQ->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE LOWER(?)", [$searchTerm])
                            ->orWhereRaw("LOWER(employee_code) LIKE LOWER(?)", [$searchTerm])
                            ->orWhereRaw("LOWER(work_email) LIKE LOWER(?)", [$searchTerm]);
                    })
                    ->orWhereHas('rows.project', function ($projectQ) use ($searchTerm) {
                        $projectQ->whereRaw("LOWER(project_name) LIKE LOWER(?)", [$searchTerm]);
                    })
                    ->orWhereHas('rows.project.client', function ($clientQ) use ($searchTerm) {
                        $clientQ->whereRaw("LOWER(name) LIKE LOWER(?)", [$searchTerm]);
                    })
                    ->orWhereRaw("LOWER(overall_status) LIKE LOWER(?)", [$searchTerm])
                    ->orWhereRaw("DATE_FORMAT(period_start, '%Y-%m-%d') LIKE ?", [$searchTerm])
                    ->orWhereRaw("DATE_FORMAT(period_end, '%Y-%m-%d') LIKE ?", [$searchTerm]);
                });
            }

            // Apply status filter
            if ($request->filled('status')) {
                $query->where('overall_status', $request->input('status'));
            }

            // Apply date range filters
            if ($request->filled('start_date')) {
                $query->where('period_end', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->where('period_start', '<=', $request->input('end_date'));
            }

            // Apply project filter
            if ($request->filled('project_id')) {
                $query->whereHas('rows', function ($q) use ($request) {
                    $q->where('project_id', $request->input('project_id'));
                });
            }

            // Apply client filter
            if ($request->filled('client_id')) {
                $query->whereHas('rows.project', function ($q) use ($request) {
                    $q->where('client_id', $request->input('client_id'));
                });
            }

            // Apply reopen tab filter
            if ($request->boolean('is_reopen_tab')) {
                $query->where('overall_status', 'reopened');
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            switch ($sortBy) {
                case 'employee_name':
                    $query->join('xxx_employees as emp', 'xxx_timesheets.employee_id', '=', 'emp.id')
                        ->orderByRaw("CONCAT(emp.first_name, ' ', emp.last_name) $sortOrder")
                        ->select('xxx_timesheets.*');
                    break;
                case 'period_start':
                case 'period_end':
                case 'overall_status':
                case 'created_at':
                case 'updated_at':
                case 'submitted_at':
                    $query->orderBy($sortBy, $sortOrder);
                    break;
                case 'total_hours':
                    // Calculate total hours on the fly
                    $query->leftJoin('xxx_timesheet_rows as tr', 'xxx_timesheets.id', '=', 'tr.timesheet_id')
                        ->groupBy('xxx_timesheets.id')
                        ->orderByRaw("SUM(
                            tr.hours_monday + tr.hours_tuesday + tr.hours_wednesday +
                            tr.hours_thursday + tr.hours_friday + tr.hours_saturday + tr.hours_sunday
                        ) $sortOrder")
                        ->select('xxx_timesheets.*');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $perPage = $request->input('per_page', 20);
            $timesheets = $query->paginate($perPage);

            // Transform using enhanced TimesheetResource
            $transformedData = $timesheets->toArray();
            $enhancedData = [];

            foreach ($timesheets->items() as $timesheet) {
                $timesheetData = (new TimesheetResource($timesheet))->resolve();

                // Add enhanced fields
                $timesheetData['employee_name'] = $timesheet->employee
                    ? $timesheet->employee->first_name . ' ' . $timesheet->employee->last_name
                    : '';

                $timesheetData['department_id'] = $timesheet->employee->department_id ?? null;
                $timesheetData['department_name'] = $timesheet->employee->department->name ?? '';

                // Get primary project and client info
                $primaryRow = $timesheet->rows->first();
                if ($primaryRow) {
                    $timesheetData['project_id'] = $primaryRow->project_id;
                    $timesheetData['project_name'] = $primaryRow->project->project_name ?? '';
                    $timesheetData['client_id'] = $primaryRow->project->client_id ?? null;
                    $timesheetData['client_name'] = $primaryRow->project->client->name ?? '';
                }

                // Calculate total hours
                $totalHours = $timesheet->rows->sum(function ($row) {
                    return $row->hours_monday + $row->hours_tuesday + $row->hours_wednesday +
                           $row->hours_thursday + $row->hours_friday + $row->hours_saturday + $row->hours_sunday;
                });
                $timesheetData['total_hours'] = $totalHours;

                // Get workflow information
                $timesheetData['current_stage'] = $this->getCurrentStage($timesheet);
                $timesheetData['workflow_step'] = $this->getWorkflowStep($timesheet);

                // Additional metadata
                $timesheetData['comments_count'] = $timesheet->chats->count();
                $timesheetData['has_attachments'] = false; // TODO: implement attachment check
                $timesheetData['is_editable'] = $this->canEditTimesheet($timesheet, $employee);

                // Add approval timestamps
                $approvedApproval = $timesheet->approvals->where('status', 'approved')->first();
                $timesheetData['approved_at'] = $approvedApproval ? $approvedApproval->acted_at : null;

                $enhancedData[] = $timesheetData;
            }

            $transformedData['data'] = $enhancedData;

            // Add current filters to response
            $transformedData['filters'] = $request->only(['search', 'status', 'start_date', 'end_date']);

            return $this->ok('Timesheets fetched successfully', $transformedData);

        } catch (Throwable $e) {
            return $this->fail('Error fetching timesheets: ' . $e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Reopened List  ───────────────────── */
    public function reopenedList(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Get timesheets that have been reopened for the employee
            $query = Timesheet::with(['rows', 'employee'])
                ->where('employee_id', $employee->id)
                ->where('overall_status', 'reopened');

            // Sort by latest first
            $query->orderBy('updated_at', 'desc');

            // Paginate results
            $timesheets = $query->paginate($request->input('per_page', 10));

            return $this->ok('Reopened timesheets retrieved successfully', $timesheets);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving reopened timesheets: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Pending Approvals  ───────────────────── */
    public function pendingApprovals(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Get timesheets where the employee is an approver with pending status
            $query = Timesheet::whereHas('approvals', function ($query) use ($employee) {
                $query->where('approver_id', $employee->id)
                    ->where('status', 'pending');
            })->with(['rows.project', 'rows.task', 'employee', 'approvals' => function ($query) use ($employee) {
                $query->where('approver_id', $employee->id);
            }]);

            // Sort by latest first
            $query->orderBy('submitted_at', 'desc');

            // Paginate results
            $timesheets = $query->paginate($request->input('per_page', 10));

            return $this->ok('Pending approval timesheets retrieved successfully', $timesheets);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving pending approvals: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Show Timesheet  ───────────────────── */
    public function show(int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Find the timesheet with minimal required relations
            $timesheet = Timesheet::with([
                'rows.project:id,project_name',
                'rows.task:id,name',
                'employee:id,first_name,last_name,email',
                'approvals.approver:id,first_name,last_name,email',
                'chats' => function ($query) {
                    $query->whereNull('parent_id'); // Root chat messages only
                },
            ])->find($id);

            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Check if user has permission to view this timesheet
            if ($timesheet->employee_id != $employee->id &&
                ! $timesheet->approvals->where('approver_id', $employee->id)->count() &&
                ! $this->isManagerOrAdmin($employee)) {
                return $this->fail('You do not have permission to view this timesheet', 403);
            }

            return $this->ok('Timesheet retrieved successfully', new TimesheetResource($timesheet));
        } catch (Throwable $e) {
            return $this->fail('Error retrieving timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Flow Track  ───────────────────── */
    public function flowTrack(int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Check if user has permission to view this timesheet
            if ($timesheet->employee_id != $employee->id &&
                ! TimesheetApproval::where('timesheet_id', $id)
                    ->where('approver_id', $employee->id)
                    ->exists() &&
                ! $this->isManagerOrAdmin($employee)) {
                return $this->fail('You do not have permission to view this timesheet', 403);
            }

            // Get workflow history
            $history = TimesheetWorkflowHistory::with('actionBy')
                ->where('timesheet_id', $id)
                ->orderBy('acted_at', 'asc')
                ->get();

            return $this->ok('Workflow history retrieved successfully', $history);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving workflow history: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Approve Timesheet  ───────────────────── */
    public function approve(Request $request, int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Find the timesheet
            $timesheet = Timesheet::with(['approvals'])->find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Lock the approval record to prevent concurrent updates
            $approval = TimesheetApproval::where('timesheet_id', $id)
                ->where('approver_id', $employee->id)
                ->where('status', 'pending')
                ->lockForUpdate() // added locking here
                ->first();

            if (! $approval) {
                return $this->fail('You do not have an active approval request for this timesheet', 403);
            }

            // Re-check the current status (if needed) before updating
            if ($approval->status !== 'pending') {
                return $this->fail('Approval status has already been updated elsewhere', 409);
            }

            // Update approval record
            $approval->status = 'approved';
            $approval->acted_at = now();
            $approval->comment = $request->input('comment');
            $approval->save();

            // Record in workflow history
            $this->addWorkflowHistory(
                $timesheet->id,
                $approval->approver_role,
                'approved',
                $request->input('comment'),
                $employee->id
            );

            // Check for pending approvals based on role and create next level approvals if needed
            if ($approval->approver_role === 'pm') {
                $pendingPmCount = TimesheetApproval::where('timesheet_id', $id)
                    ->where('approver_role', 'pm')
                    ->where('status', 'pending')
                    ->count();
                if ($pendingPmCount === 0) {
                    $this->createDMApprovals($timesheet);
                }
            } elseif ($approval->approver_role === 'dm') {
                $pendingDmCount = TimesheetApproval::where('timesheet_id', $id)
                    ->where('approver_role', 'dm')
                    ->where('status', 'pending')
                    ->count();
                if ($pendingDmCount === 0) {
                    $this->createGMApprovals($timesheet);
                }
            } elseif ($approval->approver_role === 'gm') {
                $timesheet->overall_status = 'approved';
                $timesheet->save();
            }

            DB::commit();

            // Return the updated timesheet
            $timesheet->load(['rows.project', 'rows.task', 'approvals.approver']);

            return $this->ok('Timesheet approved successfully', $timesheet);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error approving timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Reject Timesheet  ───────────────────── */
    public function reject(Request $request, int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Find user's approval record
            $approval = TimesheetApproval::where('timesheet_id', $id)
                ->where('approver_id', $employee->id)
                ->where('status', 'pending')
                ->first();

            if (! $approval) {
                return $this->fail('You do not have an active approval request for this timesheet', 403);
            }

            // Update approval record
            $approval->status = 'rejected';
            $approval->acted_at = now();
            $approval->comment = $request->input('comment');
            $approval->save();

            // Update all other pending approvals to auto_closed
            TimesheetApproval::where('timesheet_id', $id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'auto_closed',
                    'acted_at' => now(),
                    'comment' => 'Automatically closed due to rejection by '.$employee->first_name.' '.$employee->last_name,
                ]);

            // Update timesheet status
            $timesheet->overall_status = 'rejected';
            $timesheet->save();

            // Record in workflow history
            $this->addWorkflowHistory(
                $timesheet->id,
                $approval->approver_role,
                'rejected',
                $request->input('comment'),
                $employee->id
            );

            DB::commit();

            // Return the updated timesheet
            $timesheet->load(['rows.project', 'rows.task', 'approvals.approver']);

            return $this->ok('Timesheet rejected successfully', $timesheet);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error rejecting timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Reopen Timesheet  ───────────────────── */
    public function reopen(Request $request, int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Only managers or the last approver who rejected can reopen
            $canReopen = $this->isManagerOrAdmin($employee);

            if (! $canReopen) {
                $lastRejection = TimesheetApproval::where('timesheet_id', $id)
                    ->where('status', 'rejected')
                    ->where('approver_id', $employee->id)
                    ->exists();

                $canReopen = $lastRejection;
            }

            if (! $canReopen) {
                return $this->fail('You do not have permission to reopen this timesheet', 403);
            }

            // Update timesheet status
            $timesheet->overall_status = 'reopened';
            $timesheet->save();

            // Delete all existing approvals
            TimesheetApproval::where('timesheet_id', $id)->delete();

            // Record in workflow history
            $this->addWorkflowHistory(
                $timesheet->id,
                $this->getUserRole($employee),
                'reopened',
                $request->input('comment'),
                $employee->id
            );

            DB::commit();

            // Return the updated timesheet
            $timesheet->load(['rows.project', 'rows.task']);

            return $this->ok('Timesheet reopened successfully', $timesheet);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->fail('Error reopening timesheet: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Chat on Timesheet  ───────────────────── */
    public function chatOnTimesheet(Request $request, int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'parent_id' => 'nullable|integer|exists:xxx_timesheet_chats,id',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Check if user has permission to chat on this timesheet
            $canChat = $timesheet->employee_id == $employee->id ||
                      TimesheetApproval::where('timesheet_id', $id)
                          ->where('approver_id', $employee->id)
                          ->exists() ||
                      $this->isManagerOrAdmin($employee);

            if (! $canChat) {
                return $this->fail('You do not have permission to chat on this timesheet', 403);
            }

            // Check parent message if provided
            $parentId = $request->input('parent_id');
            if ($parentId) {
                $parentChat = TimesheetChat::where('id', $parentId)
                    ->where('timesheet_id', $id)
                    ->first();

                if (! $parentChat) {
                    return $this->fail('Parent message not found in this timesheet', 404);
                }
            }

            // Create chat message
            $chat = TimesheetChat::create([
                'timesheet_id' => $id,
                'parent_id' => $parentId,
                'sender_id' => $employee->id,
                'sender_role' => $this->getUserRole($employee),
                'message' => $request->input('message'),
            ]);

            // Return the created chat message
            $chat->load('sender');

            return $this->ok('Chat message posted successfully', $chat, 201);
        } catch (Throwable $e) {
            return $this->fail('Error posting chat message: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  List Chat Messages  ───────────────────── */
    public function listChat(int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Check if user has permission to view this timesheet's chat
            $canViewChat = $timesheet->employee_id == $employee->id ||
                          TimesheetApproval::where('timesheet_id', $id)
                              ->where('approver_id', $employee->id)
                              ->exists() ||
                          $this->isManagerOrAdmin($employee);

            if (! $canViewChat) {
                return $this->fail('You do not have permission to view this timesheet\'s chat', 403);
            }

            // Get all chat messages in a nested structure
            $rootMessages = TimesheetChat::with(['sender', 'replies.sender', 'replies.replies.sender'])
                ->where('timesheet_id', $id)
                ->whereNull('parent_id')
                ->orderBy('created_at', 'asc')
                ->get();

            return $this->ok('Chat messages retrieved successfully', $rootMessages);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving chat messages: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Pending PM Approvals  ───────────────────── */
    public function pendingPM(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Check if user has Admin role for broader access
            $hasAdminRole = $employee->activeUserRoles()
                ->whereHas('role', function ($query) {
                    $query->where('name', 'Admin');
                })
                ->exists();

            // Check if user is a project manager
            $isProjectManager = ProjectManager::where('employee_id', $employee->id)->exists();

            if ($hasAdminRole) {
                // Admin users can see all pending PM approvals
                $query = Timesheet::whereHas('approvals', function ($query) {
                    $query->where('approver_role', 'pm')
                        ->where('status', 'pending');
                })->with([
                    'rows.project:id,project_name',
                    'rows.task:id,name',
                    'employee:id,first_name,last_name,email',
                    'approvals' => function ($query) {
                        $query->where('approver_role', 'pm')->with('approver:id,first_name,last_name,email');
                    }
                ]);
            } elseif ($isProjectManager) {
                // Project managers see timesheets assigned to them for approval
                $query = Timesheet::whereHas('approvals', function ($query) use ($employee) {
                    $query->where('approver_id', $employee->id)
                        ->where('approver_role', 'pm')
                        ->where('status', 'pending');
                })->with([
                    'rows.project:id,project_name',
                    'rows.task:id,name',
                    'employee:id,first_name,last_name,email',
                    'approvals' => function ($query) use ($employee) {
                        $query->where('approver_id', $employee->id)->with('approver:id,first_name,last_name,email');
                    }
                ]);
            } else {
                // Other authenticated users can access but see limited/no data
                $query = Timesheet::whereHas('approvals', function ($query) use ($employee) {
                    $query->where('approver_id', $employee->id)
                        ->where('approver_role', 'pm')
                        ->where('status', 'pending');
                })->with([
                    'rows.project:id,project_name',
                    'rows.task:id,name',
                    'employee:id,first_name,last_name,email',
                    'approvals' => function ($query) use ($employee) {
                        $query->where('approver_id', $employee->id)->with('approver:id,first_name,last_name,email');
                    }
                ]);
            }

            // Sort by latest first
            $query->orderBy('submitted_at', 'desc');

            // Paginate results
            $timesheets = $query->paginate($request->input('per_page', 10));

            // Transform using TimesheetResource collection
            $transformedData = $timesheets->toArray();
            $transformedData['data'] = TimesheetResource::collection($timesheets->items())->resolve();

            return $this->ok('PM pending approval timesheets retrieved successfully', $transformedData);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving PM pending approvals: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Pending DM Approvals  ───────────────────── */
    public function pendingDM(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Check if user is a department manager
            $isDepartmentManager = DepartmentManager::where('employee_id', $employee->id)->exists();

            // Return empty array if user doesn't have DM permissions
            if (! $isDepartmentManager && ! $this->isManagerOrAdmin($employee)) {
                return $this->ok('DM pending approval timesheets retrieved successfully', []);
            }

            // Get timesheets pending DM approval where this user is an approver
            $query = Timesheet::whereHas('approvals', function ($query) use ($employee) {
                $query->where('approver_id', $employee->id)
                    ->where('approver_role', 'dm')
                    ->where('status', 'pending');
            })->with(['rows.project', 'rows.task', 'employee', 'approvals' => function ($query) use ($employee) {
                $query->where('approver_id', $employee->id);
            }]);

            // Sort by latest first
            $query->orderBy('submitted_at', 'desc');

            // Paginate results
            $timesheets = $query->paginate($request->input('per_page', 10));

            return $this->ok('DM pending approval timesheets retrieved successfully', $timesheets);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving DM pending approvals: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Pending GM Approvals  ───────────────────── */
    public function pendingGM(Request $request): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Check if user is a general manager
            $isGeneralManager = $employee->role && in_array(strtolower($employee->role->name), ['gm', 'ceo']);

            // Return empty array if user doesn't have GM permissions
            if (! $isGeneralManager) {
                return $this->ok('GM pending approval timesheets retrieved successfully', []);
            }

            // Get timesheets pending GM approval where this user is an approver
            $query = Timesheet::whereHas('approvals', function ($query) use ($employee) {
                $query->where('approver_id', $employee->id)
                    ->where('approver_role', 'gm')
                    ->where('status', 'pending');
            })->with(['rows.project', 'rows.task', 'employee', 'approvals' => function ($query) use ($employee) {
                $query->where('approver_id', $employee->id);
            }]);

            // Sort by latest first
            $query->orderBy('submitted_at', 'desc');

            // Paginate results
            $timesheets = $query->paginate($request->input('per_page', 10));

            return $this->ok('GM pending approval timesheets retrieved successfully', $timesheets);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving GM pending approvals: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Workflow Status  ───────────────────── */
    public function workflowStatus(int $id): JsonResponse
    {
        // Get the authenticated employee
        $employee = Auth::user();
        if (! $employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            // Find the timesheet
            $timesheet = Timesheet::find($id);
            if (! $timesheet) {
                return $this->fail('Timesheet not found', 404);
            }

            // Check if user has permission to view this timesheet
            $hasPermission = $timesheet->employee_id == $employee->id ||
                TimesheetApproval::where('timesheet_id', $id)
                    ->where('approver_id', $employee->id)
                    ->exists() ||
                $this->isManagerOrAdmin($employee);

            if (! $hasPermission) {
                return $this->fail('You do not have permission to view this timesheet', 403);
            }

            // Get all approvals for this timesheet
            $approvals = TimesheetApproval::with('approver')
                ->where('timesheet_id', $id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Get workflow history
            $history = TimesheetWorkflowHistory::with('actionBy')
                ->where('timesheet_id', $id)
                ->orderBy('acted_at', 'asc')
                ->get();

            // Group approvals by role
            $approvalsByRole = $approvals->groupBy('approver_role');

            // Calculate approval stage and status
            $workflowStatus = [
                'current_stage' => $timesheet->overall_status === 'approved' ? 'completed' :
                                  ($timesheet->overall_status === 'rejected' ? 'rejected' :
                                  ($timesheet->overall_status === 'reopened' ? 'reopened' :
                                  ($approvalsByRole->has('gm') ? 'gm' :
                                  ($approvalsByRole->has('dm') ? 'dm' : 'pm')))),
                'pm_approvals' => [
                    'total' => $approvalsByRole->has('pm') ? $approvalsByRole['pm']->count() : 0,
                    'approved' => $approvalsByRole->has('pm') ? $approvalsByRole['pm']->where('status', 'approved')->count() : 0,
                    'pending' => $approvalsByRole->has('pm') ? $approvalsByRole['pm']->where('status', 'pending')->count() : 0,
                    'rejected' => $approvalsByRole->has('pm') ? $approvalsByRole['pm']->where('status', 'rejected')->count() : 0,
                    'details' => $approvalsByRole->has('pm') ? $approvalsByRole['pm'] : [],
                ],
                'dm_approvals' => [
                    'total' => $approvalsByRole->has('dm') ? $approvalsByRole['dm']->count() : 0,
                    'approved' => $approvalsByRole->has('dm') ? $approvalsByRole['dm']->where('status', 'approved')->count() : 0,
                    'pending' => $approvalsByRole->has('dm') ? $approvalsByRole['dm']->where('status', 'pending')->count() : 0,
                    'rejected' => $approvalsByRole->has('dm') ? $approvalsByRole['dm']->where('status', 'rejected')->count() : 0,
                    'details' => $approvalsByRole->has('dm') ? $approvalsByRole['dm'] : [],
                ],
                'gm_approvals' => [
                    'total' => $approvalsByRole->has('gm') ? $approvalsByRole['gm']->count() : 0,
                    'approved' => $approvalsByRole->has('gm') ? $approvalsByRole['gm']->where('status', 'approved')->count() : 0,
                    'pending' => $approvalsByRole->has('gm') ? $approvalsByRole['gm']->where('status', 'pending')->count() : 0,
                    'rejected' => $approvalsByRole->has('gm') ? $approvalsByRole['gm']->where('status', 'rejected')->count() : 0,
                    'details' => $approvalsByRole->has('gm') ? $approvalsByRole['gm'] : [],
                ],
                'history' => $history,
            ];

            return $this->ok('Workflow status retrieved successfully', $workflowStatus);
        } catch (Throwable $e) {
            return $this->fail('Error retrieving workflow status: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Post Chat Message  ───────────────────── */
    public function postChat(Request $request, int $id): JsonResponse
    {
        // Renamed from chatOnTimesheet to match API route definition
        return $this->chatOnTimesheet($request, $id);
    }

    /* ─────────────────────  Helper Functions  ───────────────────── */

    /**
     * Check if employee is a manager or admin
     */
    private function isManagerOrAdmin(Employee $employee): bool
    {
        if (! $employee->role) {
            return false;
        }

        return in_array(strtolower($employee->role->name), ['admin', 'manager', 'hr', 'gm', 'ceo']);
    }

    /* ─────────────────────  Filter Options Endpoint  ───────────────────── */
    public function filterOptions(Request $request): JsonResponse
    {
        $employee = Auth::user();
        if (!$employee) {
            return $this->fail('Unauthorized access', 401);
        }

        try {
            $canViewAll = $this->isManagerOrAdmin($employee);
            $isDepartmentManager = $this->isDepartmentManager($employee);

            // Get available statuses with counts
            $statusQuery = Timesheet::select('overall_status', DB::raw('count(*) as count'));

            if (!$canViewAll) {
                if ($isDepartmentManager) {
                    $managedDepartmentIds = DepartmentManager::where('employee_id', $employee->id)
                        ->where('is_active', true)
                        ->pluck('department_id');

                    $statusQuery->whereHas('employee', function ($q) use ($managedDepartmentIds, $employee) {
                        $q->where(function ($subQ) use ($managedDepartmentIds, $employee) {
                            $subQ->whereIn('department_id', $managedDepartmentIds)
                                ->orWhere('id', $employee->id);
                        });
                    });
                } else {
                    $statusQuery->where('employee_id', $employee->id);
                }
            }

            $statuses = $statusQuery->groupBy('overall_status')->get()->map(function ($status) {
                return [
                    'value' => $status->overall_status,
                    'label' => ucfirst(str_replace('_', ' ', $status->overall_status)),
                    'count' => $status->count
                ];
            });

            // Get employees (if user has permission)
            $employees = collect();
            if ($canViewAll || $isDepartmentManager) {
                $employeeQuery = Employee::select('id', 'first_name', 'last_name', 'employee_code', 'department_id')
                    ->with('department:id,name');

                if (!$canViewAll && $isDepartmentManager) {
                    $managedDepartmentIds = DepartmentManager::where('employee_id', $employee->id)
                        ->where('is_active', true)
                        ->pluck('department_id');

                    $employeeQuery->where(function ($q) use ($managedDepartmentIds, $employee) {
                        $q->whereIn('department_id', $managedDepartmentIds)
                            ->orWhere('id', $employee->id);
                    });
                }

                $employees = $employeeQuery->get()->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'employee_code' => $emp->employee_code,
                        'department' => $emp->department->name ?? ''
                    ];
                });
            }

            // Get projects (based on user's accessible timesheets)
            $projectQuery = Project::select('id', 'project_name', 'client_id')
                ->with('client:id,name')
                ->whereHas('timesheetRows.timesheet', function ($q) use ($employee, $canViewAll, $isDepartmentManager) {
                    if (!$canViewAll) {
                        if ($isDepartmentManager) {
                            $managedDepartmentIds = DepartmentManager::where('employee_id', $employee->id)
                                ->where('is_active', true)
                                ->pluck('department_id');

                            $q->whereHas('employee', function ($subQ) use ($managedDepartmentIds, $employee) {
                                $subQ->where(function ($innerQ) use ($managedDepartmentIds, $employee) {
                                    $innerQ->whereIn('department_id', $managedDepartmentIds)
                                        ->orWhere('id', $employee->id);
                                });
                            });
                        } else {
                            $q->where('employee_id', $employee->id);
                        }
                    }
                });

            $projects = $projectQuery->get()->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->project_name,
                    'client_name' => $project->client->name ?? ''
                ];
            });

            // Get clients (from accessible projects)
            $clients = $projects->where('client_name', '!=', '')
                ->unique('client_name')
                ->map(function ($project) {
                    return [
                        'name' => $project['client_name']
                    ];
                })
                ->values();

            // Get departments (if user has permission)
            $departments = collect();
            if ($canViewAll || $isDepartmentManager) {
                $departments = Department::select('id', 'name')->get();
            }

            // Get date range
            $dateQuery = Timesheet::select(
                DB::raw('MIN(period_start) as min_date'),
                DB::raw('MAX(period_end) as max_date')
            );

            if (!$canViewAll) {
                if ($isDepartmentManager) {
                    $managedDepartmentIds = DepartmentManager::where('employee_id', $employee->id)
                        ->where('is_active', true)
                        ->pluck('department_id');

                    $dateQuery->whereHas('employee', function ($q) use ($managedDepartmentIds, $employee) {
                        $q->where(function ($subQ) use ($managedDepartmentIds, $employee) {
                            $subQ->whereIn('department_id', $managedDepartmentIds)
                                ->orWhere('id', $employee->id);
                        });
                    });
                } else {
                    $dateQuery->where('employee_id', $employee->id);
                }
            }

            $dateRange = $dateQuery->first();

            $filterOptions = [
                'statuses' => $statuses,
                'employees' => $employees,
                'projects' => $projects,
                'clients' => $clients,
                'departments' => $departments,
                'date_ranges' => [
                    'min_date' => $dateRange->min_date,
                    'max_date' => $dateRange->max_date
                ]
            ];

            return $this->ok('Filter options retrieved successfully', $filterOptions);

        } catch (Throwable $e) {
            return $this->fail('Error retrieving filter options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add a record to the workflow history
     */
    private function addWorkflowHistory(int $timesheetId, string $stage, string $action, ?string $comment, int $actedBy): void
    {
        TimesheetWorkflowHistory::create([
            'timesheet_id' => $timesheetId,
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment,
            'acted_by' => $actedBy,
            'acted_at' => now(),
        ]);
    }

    /**
     * Create Department Manager approvals for a timesheet
     */
    private function createDMApprovals(Timesheet $timesheet): void
    {
        $employee = Employee::with('department')->find($timesheet->employee_id);
        if ($employee && $employee->department) {
            $deptManagers = DepartmentManager::where('department_id', $employee->department_id)->get();
            if ($deptManagers->isEmpty()) {
                $timesheet->overall_status = 'approved';
                $timesheet->save();
                $this->addWorkflowHistory(
                    $timesheet->id,
                    'gm',
                    'approved', // Changed from 'auto_approved' to 'approved'
                    'No department managers found, timesheet automatically approved',
                    $timesheet->employee_id
                );

                return;
            }
            foreach ($deptManagers as $dm) {
                $existingApproval = TimesheetApproval::where('timesheet_id', $timesheet->id)
                    ->where('approver_id', $dm->employee_id)
                    ->lockForUpdate() // added locking
                    ->first();
                if (! $existingApproval || $existingApproval->approver_role !== 'dm') {
                    TimesheetApproval::create([
                        'timesheet_id' => $timesheet->id,
                        'approver_id' => $dm->employee_id,
                        'approver_role' => 'dm',
                        'status' => 'pending',
                    ]);
                }
            }
        } else {
            $timesheet->overall_status = 'approved';
            $timesheet->save();
            $this->addWorkflowHistory(
                $timesheet->id,
                'gm',
                'approved', // Changed from 'auto_approved' to 'approved'
                'No department found for employee, timesheet automatically approved',
                $timesheet->employee_id
            );
        }
    }

    /**
     * Create General Manager approvals for a timesheet.
     */
    private function createGMApprovals(Timesheet $timesheet): void
    {
        $gmEmployees = \App\Models\Employee::whereHas('role', function ($query) {
            $query->whereIn('name', ['gm', 'ceo']);
        })->get();

        if ($gmEmployees->isEmpty()) {
            $timesheet->overall_status = 'approved';
            $timesheet->save();
            $this->addWorkflowHistory(
                $timesheet->id,
                'gm',
                'approved', // Changed from 'auto_approved' to 'approved'
                'No general managers found, timesheet automatically approved',
                $timesheet->employee_id
            );

            return;
        }

        foreach ($gmEmployees as $gm) {
            // Lock each existing GM approval to prevent race conditions
            $existingApproval = TimesheetApproval::where('timesheet_id', $timesheet->id)
                ->where('approver_id', $gm->id)
                ->lockForUpdate() // added locking
                ->first();
            if (! $existingApproval) {
                TimesheetApproval::create([
                    'timesheet_id' => $timesheet->id,
                    'approver_id' => $gm->id,
                    'approver_role' => 'gm',
                    'status' => 'pending',
                ]);
            } else {
                if ($existingApproval->status !== 'approved') {
                    $existingApproval->status = 'approved';
                    $existingApproval->acted_at = now();
                    $existingApproval->save();
                }
            }
        }
    }

    /**
     * Get user role for chat/workflow purposes
     */
    private function getUserRole(Employee $employee): string
    {
        $employeeid = Timesheet::where('employee_id', $employee->id)->exists();

        if ($employeeid) {
            return 'employee';
        }

        // Check if employee is a project manager
        $isPM = ProjectManager::where('employee_id', $employee->id)->exists();
        // Check if employee is a department manager
        $isDM = DepartmentManager::where('employee_id', $employee->id)->exists();

        if ($isPM && $isDM) {
            return 'dm';
        }

        if ($isPM) {
            return 'pm';
        }

        if ($isDM) {
            return 'dm';
        }

        return 'gm';
    }

    /**
     * Check if employee is a department manager
     */
    private function isDepartmentManager(Employee $employee): bool
    {
        return DepartmentManager::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get current workflow stage
     */
    private function getCurrentStage(Timesheet $timesheet): string
    {
        switch ($timesheet->overall_status) {
            case 'draft':
            case 'reopened':
                return 'employee';
            case 'submitted':
                return 'in_review';
            case 'approved':
                return 'approved';
            case 'rejected':
                return 'rejected';
            case 'in_review':
                // Check latest approval stage
                $latestApproval = $timesheet->approvals()
                    ->where('status', 'pending')
                    ->orderBy('created_at', 'desc')
                    ->first();

                return $latestApproval ? $latestApproval->approver_role : 'pm';
            default:
                return 'employee';
        }
    }

    /**
     * Get workflow step number
     */
    private function getWorkflowStep(Timesheet $timesheet): int
    {
        $approvedCount = $timesheet->approvals()
            ->where('status', 'approved')
            ->count();

        return $approvedCount + 1;
    }

    /**
     * Check if timesheet can be edited by current user
     */
    private function canEditTimesheet(Timesheet $timesheet, Employee $employee): bool
    {
        // Owner can edit if in draft or reopened status
        if ($timesheet->employee_id == $employee->id) {
            return in_array($timesheet->overall_status, ['draft', 'reopened']);
        }

        // Managers can reopen approved/rejected timesheets
        return $this->isManagerOrAdmin($employee);
    }
}
