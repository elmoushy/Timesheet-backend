<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PageRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        // Define role IDs for easier reference
        $roles = [
            'Admin' => 1,
            'Employee' => 2,
            'Developer' => 3,
            'Department Manager' => 4,
            'Project Manager' => 5,
            'Sales' => 6,
            'HR' => 7,
            'General Manager' => 8,
        ];

        // Get page IDs by name
        $pages = DB::table('xxx_pages')->pluck('id', 'name');

        $permissions = [];

        // Define page-role mappings based on comments from PagesTableSeeder
        $pageRoleMappings = [
            'Login' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all existed roles
            'Dashboard' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all existed roles
            'Home' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all exist roles
            'Applications' => ['Admin', 'Sales'], // Admin and Sales Manager roles
            'Clients' => ['Admin', 'Sales'], // Admin and Sales Manager roles
            'Departments' => ['Admin'], // Admin roles
            'Employees' => ['Admin', 'Sales'], // Admin and Sales manager roles
            'Projects' => ['Admin', 'HR'], // Admin and HR
            'Tasks' => ['Admin'], // Admin
            'TimeSheet' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all
            'Support' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all
            'TimeManagement' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all
            'DepartmentTimeManager' => ['Admin', 'Department Manager'], // admin and Department Manager Role
            'Manager' => ['Department Manager', 'Project Manager', 'Admin'], // Department Manager and Project Manager and Admin
            'ProjectEmployeeRequest' => ['Project Manager', 'Admin'], // Project Manager and Admin
            'PendingPMApprovals' => ['Project Manager'], // Project Manager
            'PendingDMApprovals' => ['Department Manager'], // Department Manager
            'PendingGMApprovals' => ['General Manager'], // General Manager
            'Settings' => ['Admin', 'HR'], // Admin and HR
            'Notifications' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all
            'AssignedToMe' => ['Admin', 'Employee', 'Developer', 'Department Manager', 'Project Manager', 'Sales', 'HR', 'General Manager'], // all
            'AddTeamMember' => ['Admin', 'HR'], // Admin and HR
            'PermissionManagement' => ['Admin', 'HR'], // Admin and HR
        ];

        // Generate permissions array
        foreach ($pageRoleMappings as $pageName => $roleNames) {
            if (isset($pages[$pageName])) {
                foreach ($roleNames as $roleName) {
                    if (isset($roles[$roleName])) {
                        $permissions[] = [
                            'page_id' => $pages[$pageName],
                            'role_id' => $roles[$roleName],
                            'is_active' => true,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
            }
        }

        // Insert permissions into the database
        if (! empty($permissions)) {
            DB::table('xxx_page_role_permissions')->insert($permissions);
            $this->command->info('Page role permissions seeded successfully! Created '.count($permissions).' permissions.');
        } else {
            $this->command->warn('No permissions were created. Please check if pages and roles exist in the database.');
        }
    }
}
