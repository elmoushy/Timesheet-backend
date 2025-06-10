<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'xxx_tasks';

    protected $fillable = [
        'name',
        'task_type',
        'department_id',
        'project_id',
        'description',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the project that this task belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get assignments of this task to employees
     */
    public function assignments()
    {
        return $this->hasMany(AssignedTask::class, 'task_id');
    }

    /**
     * Get employees assigned to this task
     */
    public function assignedEmployees()
    {
        return $this->hasManyThrough(
            Employee::class,
            AssignedTask::class,
            'task_id',     // Foreign key on AssignedTask table
            'id',          // Foreign key on Employee table
            'id',          // Local key on Task table
            'assigned_to'  // Local key on AssignedTask table
        );
    }

    /**
     * Check if this task is assigned to a specific employee
     */
    public function isAssignedTo(int $employeeId): bool
    {
        return $this->assignments()->where('assigned_to', $employeeId)->exists();
    }

    /**
     * Get assignment for a specific employee
     */
    public function getAssignmentFor(int $employeeId): ?AssignedTask
    {
        return $this->assignments()->where('assigned_to', $employeeId)->first();
    }

    /**
     * Scope to get tasks available for assignment (tasks in the same department)
     */
    public function scopeAvailableForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
