<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'title',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewer_id',
        'rejection_reason',
        'return_reason',
        'total_amount_egp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'total_amount_egp' => 'decimal:2',
    ];

    /**
     * Get the employee that owns the expense.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the reviewer that reviewed the expense.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    /**
     * Get the expense items for the expense.
     */
    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * Calculate and update total amount in EGP.
     */
    public function calculateTotalAmount(): void
    {
        $total = $this->expenseItems->sum(function ($item) {
            if ($item->currency === 'EGP') {
                return $item->amount;
            }

            return $item->amount * $item->currency_rate;
        });

        $this->update(['total_amount_egp' => $total]);
    }

    /**
     * Get status counts for filtering.
     */
    public static function getStatusCounts(array $filters = []): array
    {
        $query = static::query();

        // Apply base filters (excluding status)
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['department'])) {
            $query->whereHas('employee.department', function ($q) use ($filters) {
                $q->where('department_name', 'like', "%{$filters['department']}%");
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
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

        // Get counts for each status
        $statusCounts = [
            'pending_approval' => (clone $query)->where('status', 'pending_approval')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'returned_for_edit' => (clone $query)->where('status', 'returned_for_edit')->count(),
        ];

        $statusCounts['all'] = array_sum($statusCounts);

        return $statusCounts;
    }

    /**
     * Scope for review filtering.
     */
    public function scopeForReview($query)
    {
        return $query->whereIn('status', ['pending_approval', 'approved', 'rejected', 'returned_for_edit']);
    }
}
