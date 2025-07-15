<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default department if it doesn't exist
        $department = Department::firstOrCreate([
            'name' => 'IT Department'
        ], [
            'description' => 'Information Technology Department',
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        // Create a default role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => 'Administrator'
        ], [
            'description' => 'System Administrator',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        // Create a default user/employee
        $employee = Employee::create([
            'employee_code' => 'EMP001',
            'first_name' => 'Admin',
            'middle_name' => null,
            'last_name' => 'User',
            'qualification' => 'Bachelor\'s Degree',
            'nationality' => 'Egyptian',
            'region' => 'Cairo',
            'address' => '123 Main Street, Cairo, Egypt',
            'work_email' => 'admin@lightidea.org',
            'personal_email' => 'admin@example.com',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'marital_status' => 'single',
            'military_status' => 'completed',
            'id_type' => 'national_id',
            'id_number' => '12345678901234',
            'id_expiry_date' => '2030-12-31',
            'employee_type' => 'full_time',
            'job_title' => 'System Administrator',
            'designation' => 'Senior Administrator',
            'grade_level' => 'A1',
            'department_id' => $department->id,
            'supervisor_id' => null,
            'role_id' => $role->id,
            'contract_start_date' => '2024-01-01',
            'contract_end_date' => null,
            'user_status' => 'active',
            'password' => 'password123', // This will be hashed by the model
            'image_path' => null,
        ]);

        $this->command->info('Default user created successfully!');
        $this->command->info('Email: admin@lightidea.org');
        $this->command->info('Password: password123');
    }
}
