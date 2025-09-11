<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExpenseItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FileController extends Controller
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
     * Upload expense attachment (handled within expense creation/update).
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'type' => ['required', 'string', 'in:expense_receipt'],
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            // Generate unique filename for identification
            $filename = 'receipt_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;

            return response()->json([
                'success' => true,
                'message' => 'File validated successfully',
                'data' => [
                    'filename' => $filename,
                    'size' => $size,
                    'mime_type' => $mimeType,
                    'original_name' => $originalName,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download/view attachment from expense item.
     */
    public function downloadExpenseItemAttachment(int $expenseItemId): Response
    {
        $expenseItem = ExpenseItem::findOrFail($expenseItemId);

        $employeeId = $this->getAuthenticatedEmployeeId();

        // Check if user can access this expense item
        if ($expenseItem->expense->employee_id !== $employeeId && !$this->canReviewExpenses()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this attachment',
            ], 403);
        }

        $fileData = $expenseItem->getFileData();

        if (!$fileData) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        return response($fileData['content'], 200, [
            'Content-Type' => $fileData['mime_type'],
            'Content-Disposition' => 'inline; filename="' . $fileData['filename'] . '"',
            'Content-Length' => $fileData['size'],
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Get attachment info from expense item.
     */
    public function getExpenseItemAttachmentInfo(int $expenseItemId): JsonResponse
    {
        $expenseItem = ExpenseItem::findOrFail($expenseItemId);

        $employeeId = $this->getAuthenticatedEmployeeId();

        // Check if user can access this expense item
        if ($expenseItem->expense->employee_id !== $employeeId && !$this->canReviewExpenses()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this attachment',
            ], 403);
        }

        if (!$expenseItem->hasAttachment()) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $expenseItem->attachment_filename,
                'mime_type' => $expenseItem->attachment_mime_type,
                'size' => $expenseItem->attachment_size,
                'download_url' => url("/api/files/expense-items/{$expenseItem->id}/attachment"),
            ],
        ]);
    }

    /**
     * Legacy file download route (for backward compatibility).
     */
    public function show(string $filename): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'File not found. Files are now stored as BLOBs.',
        ], 404);
    }

    /**
     * Legacy file delete route (for backward compatibility).
     */
    public function delete(string $filename): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Files are now stored as BLOBs and managed through expense items.',
        ], 404);
    }

    /**
     * Check if current user can review expenses.
     */
    private function canReviewExpenses(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $allowedRoles = ['Admin', 'Department Manager'];
        $userRole = $user->role?->name ?? '';
        $userRoles = $user->userRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allUserRoles = array_merge([$userRole], $userRoles);

        return count(array_intersect($allowedRoles, $allUserRoles)) > 0;
    }
}
