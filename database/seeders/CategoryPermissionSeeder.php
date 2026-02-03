<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Seed category-related permissions for POS system.
 * This can be run independently to add category permissions to existing roles.
 */
class CategoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        $this->command->info('Adding category permissions...');

        // Define category permissions
        $categoryPermissions = [
            'view_categories' => 'View product categories',
            'create_category' => 'Create new categories',
            'edit_category' => 'Edit category details',
            'delete_category' => 'Delete categories',
        ];

        // Create permissions if they don't exist
        foreach ($categoryPermissions as $permission => $description) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api']
            );
            $this->command->info("  ✓ Permission created: {$permission}");
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        // Clear cache
        app()['cache']->forget('spatie.permission.cache');

        $this->command->info('✓ Category permissions added successfully.');
    }

    private function assignPermissionsToRoles(): void
    {
        // Cashier: Can only view categories
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo('view_categories');
            $this->command->info('  ✓ Cashier role: view_categories');
        }

        // Manager: Can view, create, edit, and delete categories
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view_categories',
                'create_category',
                'edit_category',
                'delete_category',
            ]);
            $this->command->info('  ✓ Manager role: all category permissions');
        }

        // Admin: Can view, create, edit, and delete categories
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'view_categories',
                'create_category',
                'edit_category',
                'delete_category',
            ]);
            $this->command->info('  ✓ Admin role: all category permissions');
        }
    }
}
