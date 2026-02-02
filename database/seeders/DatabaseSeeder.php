<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleSeeder::class);

        // Seed branches and assign users
        $this->call(BranchSeeder::class);

        // Seed products
        $this->call(ProductSeeder::class);

        // Create test user with cashier role
        $user = User::firstOrCreate(
            ['email' => 'cashier@example.com'],
            [
                'name' => 'Test Cashier',
                'password' => bcrypt('password'),
            ]
        );
        if (! $user->hasRole('cashier')) {
            $user->assignRole('cashier');
        }

        // Create test manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Test Manager',
                'password' => bcrypt('password'),
            ]
        );
        if (! $manager->hasRole('manager')) {
            $manager->assignRole('manager');
        }

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
