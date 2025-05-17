<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'xxx_tasks';

    protected $fillable = [
        'name',
        'task_type',
        'department_id',
        'project_id',
        'description',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the project that this task belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
