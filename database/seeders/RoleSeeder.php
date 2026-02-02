<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Seed default roles and permissions for POS system.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Define roles
        $roles = [
            'cashier' => 'Cashier - can ring up sales and process payments',
            'manager' => 'Manager - can manage users and view reports',
            'admin' => 'Administrator - full system access',
        ];

        // Create roles
        foreach ($roles as $role => $description) {
            Role::firstOrCreate(['name' => $role], ['guard_name' => 'api']);
        }

        // Define permissions by category
        $permissions = [
            // Sales/Orders permissions
            'view_orders' => 'View orders',
            'create_order' => 'Create new orders',
            'edit_order' => 'Edit existing orders',
            'delete_order' => 'Delete orders',
            'complete_order' => 'Complete/checkout orders',

            // Payments
            'record_payment' => 'Record payment for order',
            'refund_payment' => 'Process refunds',
            'view_payments' => 'View payment history',

            // Products/Stock
            'view_products' => 'View products',
            'create_product' => 'Create new products',
            'edit_product' => 'Edit product details',
            'delete_product' => 'Delete products',
            'manage_stock' => 'Manage inventory levels',
            'view_stock' => 'View stock information',

            // Users
            'view_users' => 'View users',
            'create_user' => 'Create new users',
            'edit_user' => 'Edit user details',
            'delete_user' => 'Delete users',
            'assign_role' => 'Assign roles to users',

            // Reports
            'view_reports' => 'View reports',
            'export_reports' => 'Export reports',

            // Settings
            'manage_settings' => 'Manage system settings',
            'view_logs' => 'View audit logs',
        ];

        // Create permissions
        foreach ($permissions as $permission => $description) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'api']);
        }

        // Assign permissions to roles
        $cashierPermissions = [
            'view_orders',
            'create_order',
            'edit_order',
            'complete_order',
            'record_payment',
            'view_payments',
            'view_products',
            'view_stock',
        ];

        $managerPermissions = [
            'view_orders',
            'create_order',
            'edit_order',
            'delete_order',
            'complete_order',
            'record_payment',
            'refund_payment',
            'view_payments',
            'view_products',
            'create_product',
            'edit_product',
            'manage_stock',
            'view_stock',
            'view_users',
            'create_user',
            'edit_user',
            'assign_role',
            'view_reports',
            'export_reports',
        ];

        $adminPermissions = array_keys($permissions); // All permissions

        // Sync permissions to roles
        Role::where('name', 'cashier')->first()?->syncPermissions($cashierPermissions);
        Role::where('name', 'manager')->first()?->syncPermissions($managerPermissions);
        Role::where('name', 'admin')->first()?->syncPermissions($adminPermissions);

        $this->command->info('✓ Roles and permissions seeded successfully.');
    }
}
