<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewPageRolePermissionSeeder extends Seeder
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

        // Get page IDs by name for the new pages only
        $pages = DB::table('xxx_pages')
            ->whereIn('name', ['Expenses', 'ExpenseReviewer'])
            ->pluck('id', 'name');

        $permissions = [];

        // Define page-role mappings for new pages
        $pageRoleMappings = [
            'Expenses' => ['Admin', 'HR', 'Employee'], // Admin, HR, and Employee roles
            'ExpenseReviewer' => ['Admin', 'Department Manager'], // Admin and Department Manager roles
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
            $this->command->info('New page role permissions seeded successfully! Created '.count($permissions).' permissions.');
        } else {
            $this->command->warn('No permissions were created. Please check if pages exist in the database.');
        }
    }
}
