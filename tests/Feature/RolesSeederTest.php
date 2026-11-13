<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_portable_and_idempotent(): void
    {
        config([
            'admin.email'    => 'seed-admin@example.com',
            'admin.password' => 'seed-password',
            'admin.name'     => 'Seed Admin',
            'admin.phone'    => '01000000001',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'seed-admin@example.com',
            'type'  => 'admin',
        ]);

        $admin = User::where('email', 'seed-admin@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole('super-admin'));

        // الصلاحيات المشتقة اتربطت بدون INSERT IGNORE (يعمل على SQLite).
        $finance = Role::where('slug', 'finance-manager')->firstOrFail();
        $this->assertTrue(
            $finance->permissions()->where('slug', 'payments.confirm')->exists()
        );

        $influencers = Role::where('slug', 'influencers-manager')->firstOrFail();
        $this->assertTrue(
            $influencers->permissions()->where('slug', 'withdrawals.view')->exists()
        );
    }
}
