<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "=== CHECKING MANAGER PERMISSIONS ===\n\n";

// Find manager role
$managerRole = Role::where('name', 'manager')->first();
if (!$managerRole) {
    echo "❌ Manager role not found!\n";
    exit(1);
}

echo "Manager Role (ID: {$managerRole->id}, Guard: {$managerRole->guard_name})\n";
echo str_repeat('-', 50) . "\n";

// Check if view_categories permission exists
$viewCategoriesPerm = Permission::where('name', 'view_categories')->get();
echo "view_categories permission records found: " . $viewCategoriesPerm->count() . "\n";
foreach ($viewCategoriesPerm as $perm) {
    echo "  - ID: {$perm->id}, Guard: {$perm->guard_name}\n";
}
echo "\n";

// Check role permissions
$rolePermissions = $managerRole->permissions;
echo "Manager role has {$rolePermissions->count()} direct permissions:\n";
$categoryPerms = $rolePermissions->filter(fn($p) => str_contains($p->name, 'categor'));
foreach ($categoryPerms as $perm) {
    echo "  ✓ {$perm->name} (Guard: {$perm->guard_name})\n";
}

if ($categoryPerms->isEmpty()) {
    echo "  ❌ NO CATEGORY PERMISSIONS FOUND!\n";
}

echo "\n" . str_repeat('-', 50) . "\n";

// Check a manager user
$manager = User::whereHas('roles', function($q) {
    $q->where('name', 'manager');
})->first();

if (!$manager) {
    echo "❌ No manager user found in database!\n";
    exit(1);
}

echo "Manager User: {$manager->name} (ID: {$manager->id}, Email: {$manager->email})\n";
echo "User roles: " . $manager->roles->pluck('name')->implode(', ') . "\n";
echo "\n";

echo "Testing permission checks:\n";
echo "  hasRole('manager'): " . ($manager->hasRole('manager') ? '✓ YES' : '❌ NO') . "\n";
echo "  can('view_categories'): " . ($manager->can('view_categories') ? '✓ YES' : '❌ NO') . "\n";
echo "  hasPermissionTo('view_categories'): " . ($manager->hasPermissionTo('view_categories') ? '✓ YES' : '❌ NO') . "\n";

echo "\n" . str_repeat('=', 50) . "\n";
