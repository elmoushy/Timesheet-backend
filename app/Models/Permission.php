<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'xxx_permissions';

    protected $fillable = [
        'name',
        'route',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the roles associated with this permission
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'xxx_permission_role', 'permission_id', 'role_id');
    }
}
