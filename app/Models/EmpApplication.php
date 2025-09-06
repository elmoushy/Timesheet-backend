<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpApplication extends Model
{
    protected $table = 'xxx_emp_application';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['employee_id', 'application_id'];

    protected $fillable = [
        'employee_id',
        'application_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}
