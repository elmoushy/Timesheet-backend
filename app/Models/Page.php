<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $table = 'xxx_pages';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the page role permissions for the page.
     */
    public function pageRolePermissions()
    {
        return $this->hasMany(PageRolePermission::class, 'page_id', 'id');
    }

    /**
     * Get the roles that have permission to access this page.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'xxx_page_role_permissions', 'page_id', 'role_id')
            ->wherePivot('is_active', true)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active pages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
