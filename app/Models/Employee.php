<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'image_path', // Hide raw binary data from JSON serialization
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
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url', 'optimized_image_url'];

    /**
     * Get the image as base64 string.
     *
     * @return string|null
     */
    public function getImageBase64Attribute()
    {
        if (isset($this->attributes['image_path']) && $this->attributes['image_path']) {
            // Ensure the stored data is valid base64
            $data = $this->attributes['image_path'];

            // If it's already base64 encoded, validate it
            if (base64_decode($data, true) !== false) {
                return $data;
            }

            // If it's binary data, encode it
            return base64_encode($data);
        }

        return null;
    }

    /**
     * Set the image from base64 string.
     *
     * @param  string|null  $value
     * @return void
     */
    public function setImageBase64Attribute($value)
    {
        if ($value) {
            // Ensure we store as base64 encoded string to avoid UTF-8 issues
            if (base64_decode($value, true) !== false) {
                // Already base64 encoded
                $this->attributes['image_path'] = $value;
            } else {
                // Encode binary data
                $this->attributes['image_path'] = base64_encode($value);
            }
        }
    }

    /**
     * Get the image URL for display.
     *
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if (isset($this->attributes['image_path']) && $this->attributes['image_path']) {
            try {
                $base64Data = $this->getImageBase64Attribute();
                if ($base64Data) {
                    $mimeType = $this->getImageMimeType() ?: 'image/jpeg';

                    return "data:{$mimeType};base64,{$base64Data}";
                }
            } catch (\Exception $e) {
                // Return null if there's an encoding issue
                return null;
            }
        }

        return null;
    }

    /**
     * Validate image data format
     *
     * @param  string  $imageData
     */
    public static function validateImageData($imageData): bool
    {
        if (empty($imageData)) {
            return false;
        }

        try {
            // Check if it's a valid image by trying to get info
            $tempFile = tempnam(sys_get_temp_dir(), 'img_validate');

            // Handle both binary and base64 data
            if (base64_decode($imageData, true) !== false) {
                // It's base64 encoded, decode it first
                $binaryData = base64_decode($imageData);
            } else {
                // It's binary data
                $binaryData = $imageData;
            }

            file_put_contents($tempFile, $binaryData);
            $imageInfo = @getimagesize($tempFile);
            unlink($tempFile);

            return $imageInfo !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get image MIME type
     */
    public function getImageMimeType(): ?string
    {
        if (! $this->hasImage()) {
            return null;
        }

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_mime');
            $base64Data = $this->getImageBase64Attribute();

            if (! $base64Data) {
                return null;
            }

            // Decode base64 data before writing to temp file
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                return null;
            }

            file_put_contents($tempFile, $imageData);
            $imageInfo = @getimagesize($tempFile);
            unlink($tempFile);

            return $imageInfo ? $imageInfo['mime'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get optimized image URL with proper MIME type
     *
     * @return string|null
     */
    public function getOptimizedImageUrlAttribute()
    {
        if (! $this->hasImage()) {
            return null;
        }

        try {
            $mimeType = $this->getImageMimeType();
            $base64Data = $this->getImageBase64Attribute();

            if (! $base64Data) {
                return null;
            }

            if ($mimeType) {
                return "data:{$mimeType};base64,{$base64Data}";
            }

            // Fallback to JPEG if MIME type detection fails
            return "data:image/jpeg;base64,{$base64Data}";
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if employee has an image.
     */
    public function hasImage(): bool
    {
        return isset($this->attributes['image_path']) &&
               ! empty($this->attributes['image_path']) &&
               $this->getImageBase64Attribute() !== null;
    }

    /**
     * Get the image size in bytes.
     */
    public function getImageSize(): int
    {
        if (isset($this->attributes['image_path']) && $this->attributes['image_path']) {
            try {
                $base64Data = $this->getImageBase64Attribute();
                if ($base64Data) {
                    // Calculate the size of the decoded image data
                    $decodedData = base64_decode($base64Data);

                    return $decodedData ? strlen($decodedData) : 0;
                }
            } catch (\Exception $e) {
                return 0;
            }
        }

        return 0;
    }

    /**
     * Hash the password before saving.
     *
     * @param  string  $value
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
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
     *
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
        return $this->belongsToMany(Role::class, 'xxx_user_roles', 'user_id', 'role_id')
            ->wherePivot('is_active', true);
    }

    /**
     * Get user roles for this employee (new system)
     */
    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'user_id', 'id');
    }

    /**
     * Get active user roles for this employee
     */
    public function activeUserRoles()
    {
        return $this->hasMany(UserRole::class, 'user_id', 'id')->active();
    }

    /**
     * Get roles assigned to this employee via the new user_roles table
     */
    public function rolesViaUserRoles()
    {
        return $this->belongsToMany(Role::class, 'xxx_user_roles', 'user_id', 'role_id')
            ->wherePivot('is_active', true)
            ->withPivot('is_active', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Get user roles that this employee has assigned to others
     */
    public function assignedUserRoles()
    {
        return $this->hasMany(UserRole::class, 'assigned_by', 'id');
    }

    /**
     * Check if employee has access to a specific page
     */
    public function hasPageAccess($pageId): bool
    {
        return $this->rolesViaUserRoles()
            ->whereHas('pages', function ($query) use ($pageId) {
                $query->where('xxx_pages.id', $pageId)
                    ->where('xxx_page_role_permissions.is_active', true);
            })
            ->exists();
    }

    /**
     * Get all pages accessible to this employee through their roles
     */
    public function accessiblePages()
    {
        $roleIds = $this->activeUserRoles()->pluck('role_id');

        return Page::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('xxx_roles.id', $roleIds)
                ->where('xxx_page_role_permissions.is_active', true);
        })->where('is_active', true)->get();
    }

    /* ─────────────────────  Task Management Relationships  ───────────────────── */

    /**
     * Get personal tasks created by this employee
     */
    public function personalTasks()
    {
        return $this->hasMany(PersonalTask::class, 'employee_id');
    }

    /**
     * Get project tasks assigned to this employee
     */
    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'employee_id');
    }

    /**
     * Get tasks assigned to this employee by managers
     */
    public function assignedTasks()
    {
        return $this->hasMany(AssignedTask::class, 'assigned_to');
    }

    /**
     * Get tasks assigned by this employee (if they are a manager)
     */
    public function tasksAssignedByMe()
    {
        return $this->hasMany(AssignedTask::class, 'assigned_by');
    }

    /**
     * Get project assignments for this employee
     */
    public function projectAssignments()
    {
        return $this->hasMany(ProjectEmployeeAssignment::class, 'employee_id');
    }

    /**
     * Get productivity analytics for this employee
     */
    public function productivityAnalytics()
    {
        return $this->hasMany(EmployeeProductivityAnalytics::class, 'employee_id');
    }

    /**
     * Get workload capacity data for this employee
     */
    public function workloadCapacity()
    {
        return $this->hasMany(EmployeeWorkloadCapacity::class, 'employee_id');
    }

    /**
     * Get current week workload capacity
     */
    public function currentWeekWorkload()
    {
        return $this->hasOne(EmployeeWorkloadCapacity::class, 'employee_id')
            ->where('week_start_date', now()->startOfWeek());
    }

    /**
     * Get task activity logs for this employee
     */
    public function taskActivityLogs()
    {
        return $this->hasMany(TaskActivityLog::class, 'employee_id');
    }

    /**
     * Get bulk operations initiated by this employee
     */
    public function bulkTaskOperations()
    {
        return $this->hasMany(BulkTaskOperation::class, 'initiated_by');
    }

    /**
     * Get all important tasks across all task types
     */
    public function importantTasks()
    {
        $personalTasks = $this->personalTasks()->important()->get()->map(function ($task) {
            $task->task_type = 'personal';

            return $task;
        });

        $projectTasks = $this->projectTasks()->important()->get()->map(function ($task) {
            $task->task_type = 'project';

            return $task;
        });

        $assignedTasks = $this->assignedTasks()->important()->get()->map(function ($task) {
            $task->task_type = 'assigned';

            return $task;
        });

        return $personalTasks->concat($projectTasks)->concat($assignedTasks);
    }

    /**
     * Get current productivity streak
     */
    public function getCurrentStreak(): int
    {
        $latestAnalytics = $this->productivityAnalytics()
            ->orderBy('date', 'desc')
            ->first();

        return $latestAnalytics ? $latestAnalytics->streak_days : 0;
    }

    /**
     * Get total tasks count by status
     */
    public function getTaskCountsByStatus(): array
    {
        $personal = $this->personalTasks()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();
        $project = $this->projectTasks()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();
        $assigned = $this->assignedTasks()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();

        $statuses = ['to-do', 'doing', 'done', 'blocked'];
        $result = [];

        foreach ($statuses as $status) {
            $result[$status] = [
                'personal' => $personal[$status] ?? 0,
                'project' => $project[$status] ?? 0,
                'assigned' => $assigned[$status] ?? 0,
                'total' => ($personal[$status] ?? 0) + ($project[$status] ?? 0) + ($assigned[$status] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Check if employee is a department manager
     */
    public function isDepartmentManager(): bool
    {
        return $this->managedDepartments()->count() > 0;
    }

    /**
     * Get employees in departments managed by this employee
     */
    public function getManagedEmployees()
    {
        if (! $this->isDepartmentManager()) {
            return collect();
        }

        $departmentIds = $this->managedDepartments()->pluck('department_id');

        return Employee::whereIn('department_id', $departmentIds)
            ->where('id', '!=', $this->id) // Exclude self
            ->get();
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

    /**
     * Get external identities for this user (SSO)
     */
    public function externalIdentities()
    {
        return $this->hasMany(ExternalIdentity::class, 'user_id');
    }

    /**
     * Get refresh tokens for this user
     */
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }

    /**
     * Get active refresh tokens for this user
     */
    public function activeRefreshTokens()
    {
        return $this->refreshTokens()
            ->where('is_revoked', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Get the expenses for the employee.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'employee_id');
    }

    /**
     * Get the expenses reviewed by this employee.
     */
    public function reviewedExpenses()
    {
        return $this->hasMany(Expense::class, 'reviewer_id');
    }

    /**
     * Find user by external identity
     */
    public static function findByExternalIdentity(string $provider, string $externalId): ?self
    {
        $identity = ExternalIdentity::findByProvider($provider, $externalId);

        return $identity ? $identity->user : null;
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->user_status === 'active';
    }
}
