<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Requests\ReviewExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\ExpenseListResource;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Get the authenticated employee ID safely.
     */
    private function getAuthenticatedEmployeeId()
    {
        $user = auth()->user();

        // If the ID is numeric, use it directly
        if (is_numeric($user->id)) {
            return (int) $user->id;
        }

        // If ID is not numeric (like email), find the employee by email
        $employee = Employee::where('work_email', $user->id)->first();
        if ($employee) {
            return $employee->id;
        }

        // Fallback: use the user's getKey() method
        return $user->getKey();
    }
    /**
     * Display a listing of the resource for the logged-in employee.
     */
    public function index(Request $request): JsonResponse
    {
        $employeeId = $this->getAuthenticatedEmployeeId();

        $query = Expense::with(['employee', 'reviewer', 'expenseItems'])
            ->where('employee_id', $employeeId);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('expenseItems', function ($itemQuery) use ($search) {
                      $itemQuery->where('description', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min($request->get('per_page', 10), 100);
        $expenses = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ExpenseResource::collection($expenses->items()),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'total_pages' => $expenses->lastPage(),
                'has_next' => $expenses->hasMorePages(),
                'has_prev' => $expenses->currentPage() > 1,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employeeId = $this->getAuthenticatedEmployeeId();

            $expense = Expense::create([
                'employee_id' => $employeeId,
                'title' => $request->title,
                'status' => 'draft',
            ]);

            $this->storeExpenseItems($expense, $request->expenses, $request);
            $expense->calculateTotalAmount();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => new ExpenseResource($expense->load(['employee', 'expenseItems'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating expense: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): JsonResponse
    {
        $employeeId = $this->getAuthenticatedEmployeeId();

        // Check if user can view this expense
        if ($expense->employee_id !== $employeeId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this expense',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new ExpenseResource($expense->load(['employee', 'reviewer', 'expenseItems'])),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete existing expense items and their attachments
            $this->deleteExpenseItemsAndAttachments($expense);

            // Update expense
            $expense->update([
                'title' => $request->title,
            ]);

            // Create new expense items
            $this->storeExpenseItems($expense, $request->expenses, $request);
            $expense->calculateTotalAmount();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => new ExpenseResource($expense->load(['employee', 'expenseItems'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating expense: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $employeeId = $this->getAuthenticatedEmployeeId();

        // Check if user can delete this expense
        if ($expense->employee_id !== $employeeId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this expense',
            ], 403);
        }

        // Only allow deletion of draft and returned_for_edit status
        if (!in_array($expense->status, ['draft', 'returned_for_edit'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete expense with current status',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Delete attachments
            $this->deleteExpenseItemsAndAttachments($expense);

            // Delete expense
            $expense->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting expense: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit expense for approval.
     */
    public function submit(Expense $expense): JsonResponse
    {
        $employeeId = $this->getAuthenticatedEmployeeId();

        // Check if user can submit this expense
        if ($expense->employee_id !== $employeeId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this expense',
            ], 403);
        }

        // Only allow submission of draft and returned_for_edit status
        if (!in_array($expense->status, ['draft', 'returned_for_edit'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit expense with current status',
            ], 422);
        }

        $expense->update([
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense submitted for approval successfully',
            'data' => [
                'id' => $expense->id,
                'status' => $expense->status,
                'submitted_at' => $expense->submitted_at->toISOString(),
            ],
        ]);
    }

    /**
     * Save as draft.
     */
    public function saveDraft(StoreExpenseRequest $request): JsonResponse
    {
        return $this->store($request); // Draft is the default status
    }

    /**
     * Get expenses for review (Manager/Reviewer endpoints) - Optimized.
     */
    public function getForReview(Request $request): JsonResponse
    {
        // Build base query with minimal eager loading for list view
        $query = Expense::with(['employee.department'])
            ->forReview(); // Use the scope we defined

        // Collect filters for status counts
        $filters = [];

        // Apply filters
        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->has('employee_id')) {
            $filters['employee_id'] = $request->employee_id;
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('department')) {
            $filters['department'] = $request->department;
            $query->whereHas('employee.department', function ($q) use ($request) {
                $q->where('department_name', 'like', "%{$request->department}%");
            });
        }

        if ($request->has('date_from')) {
            $filters['date_from'] = $request->date_from;
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $filters['date_to'] = $request->date_to;
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $filters['search'] = $request->search;
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($empQuery) use ($search) {
                      $empQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('expenseItems', function ($itemQuery) use ($search) {
                      $itemQuery->where('description', 'like', "%{$search}%");
                  });
            });
        }

        // Pagination settings
        $perPage = min($request->get('per_page', 5), 50);

        // Load expense items for metadata calculation only
        $query->with('expenseItems:id,expense_id,currency,attachment_blob');

        $expenses = $query->orderBy('submitted_at', 'desc')->paginate($perPage);

        // Get status counts for filter tabs
        $statusCounts = Expense::getStatusCounts($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => ExpenseListResource::collection($expenses->items()),
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'total_pages' => $expenses->lastPage(),
                    'has_next_page' => $expenses->hasMorePages(),
                    'has_prev_page' => $expenses->currentPage() > 1,
                ],
                'filters' => [
                    'status_counts' => $statusCounts,
                ],
            ],
        ]);
    }

    /**
     * Get single expense for review.
     */
    public function getForReviewSingle(Expense $expense): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ExpenseResource($expense->load(['employee.department', 'employee.supervisor', 'reviewer', 'expenseItems'])),
        ]);
    }

    /**
     * Approve expense.
     */
    public function approve(ReviewExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Expense is not pending approval',
            ], 422);
        }

        $reviewerId = $this->getAuthenticatedEmployeeId();
        $currentUser = auth()->user();

        $expense->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewer_id' => $reviewerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $expense->id,
                'status' => $expense->status,
                'approved_at' => $expense->reviewed_at->toISOString(),
                'approved_by' => $currentUser->first_name . ' ' . $currentUser->last_name,
                'comment' => $request->get('comments', null),
            ],
        ]);
    }

    /**
     * Reject expense.
     */
    public function reject(ReviewExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Expense is not pending approval',
            ], 422);
        }

        $reviewerId = $this->getAuthenticatedEmployeeId();
        $currentUser = auth()->user();

        $expense->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewer_id' => $reviewerId,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $expense->id,
                'status' => $expense->status,
                'rejected_at' => $expense->reviewed_at->toISOString(),
                'rejected_by' => $currentUser->first_name . ' ' . $currentUser->last_name,
                'rejection_reason' => $expense->rejection_reason,
            ],
        ]);
    }

    /**
     * Return expense for edit.
     */
    public function returnForEdit(ReviewExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Expense is not pending approval',
            ], 422);
        }

        $reviewerId = $this->getAuthenticatedEmployeeId();
        $currentUser = auth()->user();

        $expense->update([
            'status' => 'returned_for_edit',
            'reviewed_at' => now(),
            'reviewer_id' => $reviewerId,
            'return_reason' => $request->return_reason,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $expense->id,
                'status' => $expense->status,
                'returned_at' => $expense->reviewed_at->toISOString(),
                'returned_by' => $currentUser->first_name . ' ' . $currentUser->last_name,
                'return_reason' => $expense->return_reason,
            ],
        ]);
    }

    /**
     * Store expense items with file uploads.
     */
    /**
     * Store expense items with file uploads.
     */
    private function storeExpenseItems(Expense $expense, array $expenseItems, Request $request): void
    {
        foreach ($expenseItems as $index => $item) {
            // Handle file upload for this expense item
            $fileKey = "attachment_{$index}";
            $expenseItem = ExpenseItem::create([
                'expense_id' => $expense->id,
                'date' => $item['date'],
                'type' => $item['type'],
                'currency' => $item['currency'],
                'amount' => $item['amount'],
                'currency_rate' => $item['currency_rate'],
                'description' => $item['description'],
            ]);

            // Store file as BLOB if provided
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $expenseItem->storeFile($file);
                $expenseItem->save();
            }
        }
    }

    /**
     * Delete expense items and their attachments.
     */
    private function deleteExpenseItemsAndAttachments(Expense $expense): void
    {
        foreach ($expense->expenseItems as $item) {
            // No need to delete files from filesystem since they're stored as BLOBs
            $item->delete();
        }
    }
}
