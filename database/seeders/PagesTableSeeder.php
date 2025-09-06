<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            // all this pages Admin Role have access
            ['name' => 'Login', 'is_active' => true], // all existed roles
            ['name' => 'Dashboard', 'is_active' => true], // all existed roles
            ['name' => 'Home', 'is_active' => true], // all exist roles
            ['name' => 'Applications', 'is_active' => true], // Admin and Sales Manager  roles
            ['name' => 'Clients', 'is_active' => true], // Admin and Sales Manager  roles
            ['name' => 'Departments', 'is_active' => true], // Admin roles
            ['name' => 'Employees', 'is_active' => true], // Admin and Sales manager roles
            ['name' => 'Projects', 'is_active' => true], // Admin and HR
            ['name' => 'Tasks', 'is_active' => true], // Admin
            ['name' => 'TimeSheet', 'is_active' => true], // all
            ['name' => 'Support', 'is_active' => true], // all
            ['name' => 'TimeManagement', 'is_active' => true], // all
            ['name' => 'DepartmentTimeManager', 'is_active' => true], // admin and Department Manager Role
            ['name' => 'Manager', 'is_active' => true], // Department Manager and Project Manager and Admin
            ['name' => 'ProjectEmployeeRequest', 'is_active' => true], // Project Manager and Admin
            ['name' => 'PendingPMApprovals', 'is_active' => true], // Project Manager
            ['name' => 'PendingDMApprovals', 'is_active' => true], // Department Manager
            ['name' => 'PendingGMApprovals', 'is_active' => true], // General Manager
            ['name' => 'Settings', 'is_active' => true], // Admin and HR
            ['name' => 'Notifications', 'is_active' => true], // all
            ['name' => 'AssignedToMe', 'is_active' => true], // all
            ['name' => 'AddTeamMember', 'is_active' => true], // Admin and HR
            ['name' => 'PermissionManagement', 'is_active' => true], // Admin and HR
        ];

        $timestamp = Carbon::now();

        // Add timestamps to each page
        foreach ($pages as &$page) {
            $page['created_at'] = $timestamp;
            $page['updated_at'] = $timestamp;
        }

        // Insert pages into the database
        DB::table('xxx_pages')->insert($pages);

        $this->command->info('Pages seeded successfully!');
    }
}
