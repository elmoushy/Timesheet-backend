<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DepartmentManager extends Pivot
{
    protected $table = 'xxx_department_managers';

    public $incrementing = false;
    protected $primaryKey = ['department_id', 'employee_id'];

    protected $fillable = [
        'department_id',
        'employee_id',
        'is_primary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
