<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'Admin',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Employee',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Developer',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Department Manager',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Project Manager',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 6,
                'name' => 'Sales',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 7,
                'name' => 'HR',
                'description' => null,
                'is_active' => true,
            ],
            [
                'id' => 8,
                'name' => 'General Manager',
                'description' => null,
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
