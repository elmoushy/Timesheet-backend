<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $table = 'xxx_projects';

    protected $fillable = [
        'client_id',
        'project_name',
        'department_id',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'project_name' => 'string',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'xxx_proj_products',
            'project_id',
            'product_id'
        );
    }

    /**
     * Get all managers for this project
     */
    public function managers()
    {
        return $this->belongsToMany(Employee::class, 'xxx_project_managers', 'project_id', 'employee_id')
            ->using(ProjectManager::class)
            ->withPivot('role', 'start_date', 'end_date')
            ->withTimestamps();
    }

    /**
     * Get lead manager for this project
     */
    public function leadManager()
    {
        return $this->managers()->wherePivot('role', 'lead');
    }

    /**
     * Get contact numbers associated with this project
     */
    public function contactNumbers()
    {
        return $this->hasMany(ClientNumber::class, 'project_id');
    }

    /**
     * Get all tasks associated with this project
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * Get employee assignments for this project
     */
    public function employeeAssignments()
    {
        return $this->hasMany(ProjectEmployeeAssignment::class, 'project_id');
    }
}
