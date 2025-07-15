<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            ['name' => 'Login', 'is_active' => true],
            ['name' => 'Dashboard', 'is_active' => true],
            ['name' => 'Home', 'is_active' => true],
            ['name' => 'Applications', 'is_active' => true],
            ['name' => 'Clients', 'is_active' => true],
            ['name' => 'Departments', 'is_active' => true],
            ['name' => 'Employees', 'is_active' => true],
            ['name' => 'Projects', 'is_active' => true],
            ['name' => 'Tasks', 'is_active' => true],
            ['name' => 'TimeSheet', 'is_active' => true],
            ['name' => 'Support', 'is_active' => true],
            ['name' => 'TimeManagement', 'is_active' => true],
            ['name' => 'DepartmentTimeManager', 'is_active' => true],
            ['name' => 'Manager', 'is_active' => true],
            ['name' => 'ProjectEmployeeRequest', 'is_active' => true],
            ['name' => 'PendingPMApprovals', 'is_active' => true],
            ['name' => 'PendingDMApprovals', 'is_active' => true],
            ['name' => 'PendingGMApprovals', 'is_active' => true],
            ['name' => 'Settings', 'is_active' => true],
            ['name' => 'Notifications', 'is_active' => true],
            ['name' => 'AssignedToMe', 'is_active' => true],
            ['name' => 'AddTeamMember', 'is_active' => true],
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
