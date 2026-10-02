<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Roles & permissions are always required. Demo schools, users and
     * students are added unless SEED_DEMO=false (use that in production and
     * create the first super admin with `php artisan app:create-super-admin`).
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (filter_var(env('SEED_DEMO', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoSeeder::class);
        }
    }
}
