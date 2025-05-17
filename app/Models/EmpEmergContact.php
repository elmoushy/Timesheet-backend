<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpEmergContact extends Model
{
    use HasFactory;

    protected $table = 'xxx_emp_emerg_contacts';

    protected $fillable = [
        'employee_id',
        'name',
        'relationship',
        'phone',
        'address',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
