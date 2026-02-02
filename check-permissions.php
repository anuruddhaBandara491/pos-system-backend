<?php

// Quick script to check cashier role permissions

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;

echo "Checking cashier role permissions:\n";
echo str_repeat('-', 50) . "\n";

$role = Role::where('name', 'cashier')->first();

if (!$role) {
    echo "❌ Cashier role NOT FOUND in database!\n";
    echo "Run: php artisan db:seed --class=RoleSeeder\n";
    exit(1);
}

echo "✓ Cashier role found (ID: {$role->id})\n\n";

$permissions = $role->permissions;

if ($permissions->isEmpty()) {
    echo "❌ Cashier role has NO permissions assigned!\n";
    echo "Run: php artisan db:seed --class=RoleSeeder\n";
    exit(1);
}

echo "✓ Cashier role has {$permissions->count()} permissions:\n";
foreach ($permissions as $permission) {
    echo "  - {$permission->name}\n";
}

echo "\n" . str_repeat('-', 50) . "\n";
echo "Testing user with cashier role:\n";

$user = \App\Models\User::role('cashier')->first();

if (!$user) {
    echo "❌ No users found with cashier role!\n";
    exit(1);
}

echo "✓ Found user: {$user->name} ({$user->email})\n";
echo "  Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
echo "  Permissions: " . $user->getAllPermissions()->pluck('name')->implode(', ') . "\n";
