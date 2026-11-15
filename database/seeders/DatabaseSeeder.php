<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Real roles and permissions are always seeded first.
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Fake demo accounts/catalog are restricted inside the seeder to local/testing.
        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
