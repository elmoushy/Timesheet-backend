<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectManager extends Pivot
{
    protected $table = 'xxx_project_managers';

    public $incrementing = false;
    protected $primaryKey = ['project_id', 'employee_id'];

    protected $fillable = [
        'project_id',
        'employee_id',
        'role',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'role' => 'string',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
