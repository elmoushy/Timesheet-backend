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
}
