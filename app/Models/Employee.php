<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class Employee extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'xxx_employees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_code',
        'first_name',
        'middle_name',
        'last_name',
        'qualification',
        'nationality',
        'region',
        'address',
        'work_email',
        'personal_email',
        'birth_date',
        'gender',
        'marital_status',
        'military_status',
        'id_type',
        'id_number',
        'id_expiry_date',
        'employee_type',
        'job_title',
        'designation',
        'grade_level',
        'department_id',
        'supervisor_id',
        'role_id',
        'contract_start_date',
        'contract_end_date',
        'user_status',
        'image_path',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'id_expiry_date' => 'date:Y-m-d',
        'contract_start_date' => 'date:Y-m-d',
        'contract_end_date' => 'date:Y-m-d',
        'gender' => 'string',
        'marital_status' => 'string',
        'military_status' => 'string',
        'id_type' => 'string',
        'employee_type' => 'string',
        'user_status' => 'string',
    ];

    /**
     * Hash the password before saving.
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Get the full name of the employee.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /**
     * Scope a query to only include active employees.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('user_status', 'active');
    }

    /**
     * Get the department associated with the employee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function supervisees()
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function phones()
    {
        return $this->hasMany(EmpPhone::class, 'employee_id');
    }

    public function emergencyContacts()
    {
        return $this->hasMany(EmpEmergContact::class, 'employee_id');
    }

    public function applications()
    {
        return $this->belongsToMany(
            Application::class,
            'xxx_emp_application',
            'employee_id',
            'application_id'
        );
    }

    /**
     * Get projects where employee is directly assigned as primary manager
     * @deprecated Use managedProjects() instead for complete management relationships
     */
    public function directlyManagedProjects()
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    /**
     * Get departments managed by this employee
     */
    public function managedDepartments()
    {
        return $this->belongsToMany(Department::class, 'xxx_department_managers', 'employee_id', 'department_id')
            ->using(DepartmentManager::class)
            ->withPivot('is_primary', 'start_date', 'end_date')
            ->withTimestamps();
    }

    /**
     * Get projects managed by this employee
     */
    public function managedProjects()
    {
        return $this->belongsToMany(Project::class, 'xxx_project_managers', 'employee_id', 'project_id')
            ->using(ProjectManager::class)
            ->withPivot('role', 'start_date', 'end_date')
            ->withTimestamps();
    }

    /**
     * Get the role directly associated with the employee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get all roles assigned to this employee via the pivot table
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'xxx_employee_role', 'employee_id', 'role_id');
    }

    /**
     * Get username field for authentication.
     * This tells Laravel which field to use for authentication.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'work_email';
    }
}
