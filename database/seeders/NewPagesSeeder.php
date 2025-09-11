<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            ['name' => 'Expenses', 'is_active' => true], // Admin, HR, and Employee roles
            ['name' => 'ExpenseReviewer', 'is_active' => true], // Admin and Department Manager roles
        ];

        $timestamp = Carbon::now();

        // Add timestamps to each page
        foreach ($pages as &$page) {
            $page['created_at'] = $timestamp;
            $page['updated_at'] = $timestamp;
        }

        // Insert pages into the database
        DB::table('xxx_pages')->insert($pages);

        $this->command->info('New pages seeded successfully!');
    }
}
