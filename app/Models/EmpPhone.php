<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpPhone extends Model
{
    use HasFactory;

    protected $table = 'xxx_emp_phones';

    protected $fillable = [
        'employee_id',
        'phone_type',
        'phone_number',
    ];

    protected $casts = [
        'phone_type' => 'string',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
