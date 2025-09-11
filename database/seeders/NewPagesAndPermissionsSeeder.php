<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NewPagesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            NewPagesSeeder::class,
            NewPageRolePermissionSeeder::class,
        ]);

        $this->command->info('All new pages and permissions seeded successfully!');
    }
}
