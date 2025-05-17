<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Project;
use App\Models\Department;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * These methods would normally go in the Employee model,
     * but are added here as helpers for demonstration.
     */

    /**
     * Check if the authenticated user is an admin
     */
    protected function isAdmin(): bool
    {
        return auth()->user()->role === 'admin';
    }

    /**
     * Check if the authenticated user is a project manager on the given project
     */
    protected function isPmOn(Project $project): bool
    {
        return $project->managers()->where('employee_id', auth()->id())->exists();
    }

    /**
     * Get departments managed by the authenticated user
     */
    protected function managedDepartments()
    {
        return auth()->user()->belongsToMany(Department::class, 'xxx_department_managers')
                    ->withPivot('is_primary');
    }
}
