<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $table = 'xxx_applications';

    protected $fillable = [
        'name',
        'department_id',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees()
    {
        return $this->belongsToMany(
            Employee::class,
            'xxx_emp_application',
            'application_id',
            'employee_id'
        );
    }
}
