<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'xxx_roles';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the employees associated with this role via direct relationship
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'role_id');
    }

    /**
     * Get the employees associated with this role via the pivot table
     */
    public function assignedEmployees()
    {
        return $this->belongsToMany(Employee::class, 'xxx_employee_role', 'role_id', 'employee_id');
    }

    /**
     * Get the permissions associated with this role
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'xxx_permission_role', 'role_id', 'permission_id');
    }

    /**
     * Get the user roles for this role
     */
    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'role_id', 'id');
    }

    /**
     * Get the active user roles for this role
     */
    public function activeUserRoles()
    {
        return $this->hasMany(UserRole::class, 'role_id', 'id')->active();
    }

    /**
     * Get the page role permissions for this role
     */
    public function pageRolePermissions()
    {
        return $this->hasMany(PageRolePermission::class, 'role_id', 'id');
    }

    /**
     * Get the pages that this role has permission to access
     */
    public function pages()
    {
        return $this->belongsToMany(Page::class, 'xxx_page_role_permissions', 'role_id', 'page_id')
            ->wherePivot('is_active', true)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Get users (employees) assigned to this role via the user_roles table
     */
    public function usersViaUserRoles()
    {
        return $this->belongsToMany(Employee::class, 'xxx_user_roles', 'role_id', 'user_id')
            ->wherePivot('is_active', true)
            ->withPivot('is_active', 'assigned_by')
            ->withTimestamps();
    }
}
