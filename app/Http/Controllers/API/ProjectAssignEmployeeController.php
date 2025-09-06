<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ProjectEmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectAssignEmployeeController extends Controller
{
    /**
     * Display assignment requests based on the authenticated user's role
     * With filtering by status and search functionality
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get departments where the user is a manager
        $managedDepartmentIds = $user->managedDepartments()->pluck('department_id');

        if ($managedDepartmentIds->isEmpty()) {
            return response()->json([
                'message' => 'You are not a manager of any department',
                'data' => [],
            ], 200);
        }

        // Get employee IDs belonging to departments managed by the user
        $departmentEmployeeIds = Employee::whereIn('department_id', $managedDepartmentIds)
            ->pluck('id');

        // Start building the query
        $query = ProjectEmployeeAssignment::whereIn('employee_id', $departmentEmployeeIds);

        // Filter by department_approval_status if provided
        if ($request->has('status') && ! empty($request->status)) {
            $status = $request->status;
            $query->where('department_approval_status', $status);
        }

        // Apply search if provided
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            })->orWhereHas('project', function ($q) use ($search) {
                $q->where('project_name', 'like', "%{$search}%");
            });
        }

        // Get the assignment requests with relationships
        $assignmentRequests = $query->with(['project', 'employee', 'requester'])
            ->orderBy('requested_at', 'desc')
            ->get();

        // Transform the data to include only the requested fields
        $transformedData = $assignmentRequests->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'department_approval_status' => $assignment->department_approval_status,
                'project_name' => $assignment->project->project_name,
                'employee' => [
                    'name' => $assignment->employee->first_name.' '.$assignment->employee->last_name,
                ],
                'requester' => [
                    'name' => $assignment->requester->first_name.' '.$assignment->requester->last_name,
                ],
            ];
        });

        return response()->json([
            'message' => 'Assignment requests retrieved successfully',
            'data' => $transformedData,
        ], 200);
    }

    /**
     * Approve an employee assignment request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $id)
    {
        $assignment = ProjectEmployeeAssignment::findOrFail($id);
        $user = Auth::user();

        // Check if user is authorized to approve this request
        $employee = Employee::find($assignment->employee_id);
        $isDepartmentManager = $user->managedDepartments()
            ->where('department_id', $employee->department_id)
            ->exists();

        if (! $isDepartmentManager) {
            return response()->json([
                'message' => 'You are not authorized to approve this request',
            ], 403);
        }

        // Approve the assignment
        $notes = $request->input('notes');
        $success = $assignment->approve($user->id, $notes);

        if ($success) {
            return response()->json([
                'message' => 'Assignment request approved successfully',
                'data' => $assignment,
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to approve assignment request',
        ], 500);
    }

    /**
     * Reject an employee assignment request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $id)
    {
        $assignment = ProjectEmployeeAssignment::findOrFail($id);
        $user = Auth::user();

        // Check if user is authorized to reject this request
        $employee = Employee::find($assignment->employee_id);
        $isDepartmentManager = $user->managedDepartments()
            ->where('department_id', $employee->department_id)
            ->exists();

        if (! $isDepartmentManager) {
            return response()->json([
                'message' => 'You are not authorized to reject this request',
            ], 403);
        }

        // Reject the assignment
        $notes = $request->input('notes');
        $success = $assignment->reject($user->id, $notes);

        if ($success) {
            return response()->json([
                'message' => 'Assignment request rejected successfully',
                'data' => $assignment,
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to reject assignment request',
        ], 500);
    }

    /**
     * Resend a rejected assignment request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function resend(Request $request, $id)
    {
        $assignment = ProjectEmployeeAssignment::findOrFail($id);
        $user = Auth::user();

        // Check if request is rejected and can be resent
        if (! $assignment->isRejected()) {
            return response()->json([
                'message' => 'Only rejected requests can be resent',
            ], 400);
        }

        // Check if user is authorized to resend this request
        if ($assignment->requested_by != $user->id &&
            ! $user->managedProjects()->where('project_id', $assignment->project_id)->exists()) {
            return response()->json([
                'message' => 'You are not authorized to resend this request',
            ], 403);
        }

        // Resend the request by updating its status to pending
        $assignment->department_approval_status = 'pending';
        $assignment->requested_at = now();
        $assignment->response_at = null;
        $assignment->approved_by = null;

        // Add notes if provided
        if ($request->has('notes')) {
            $assignment->notes = $request->input('notes');
        }

        if ($assignment->save()) {
            return response()->json([
                'message' => 'Assignment request resent successfully',
                'data' => $assignment,
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to resend assignment request',
        ], 500);
    }
}
