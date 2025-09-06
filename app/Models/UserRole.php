<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $table = 'xxx_user_roles';

    protected $primaryKey = 'user_roles_id';

    protected $fillable = [
        'role_id',
        'user_id',
        'is_active',
        'assigned_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the role that owns the user role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * Get the user (employee) that owns the user role.
     */
    public function user()
    {
        return $this->belongsTo(Employee::class, 'user_id', 'id');
    }

    /**
     * Get the employee who assigned this role.
     */
    public function assignedBy()
    {
        return $this->belongsTo(Employee::class, 'assigned_by', 'id');
    }

    /**
     * Scope a query to only include active user roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
