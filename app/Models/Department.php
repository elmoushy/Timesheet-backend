<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'xxx_departments';

    protected $fillable = [
        'name',
        'notes',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'department_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'department_id');
    }

    /**
     * Get all managers for this department
     */
    public function managers()
    {
        return $this->belongsToMany(Employee::class, 'xxx_department_managers', 'department_id', 'employee_id')
            ->using(DepartmentManager::class)
            ->withPivot('is_primary', 'start_date', 'end_date')
            ->withTimestamps();
    }

    /**
     * Get primary manager for this department
     */
    public function primaryManager()
    {
        return $this->managers()->wherePivot('is_primary', true);
    }
}
