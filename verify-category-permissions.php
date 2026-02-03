<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== CATEGORY PERMISSIONS VERIFICATION ===\n\n";

// Check Cashier
echo "CASHIER ROLE:\n";
echo str_repeat('-', 50) . "\n";
$cashier = User::role('cashier')->first();
if ($cashier) {
    $permissions = $cashier->getAllPermissions()->pluck('name')->toArray();
    $categoryPerms = array_filter($permissions, fn($p) => str_contains($p, 'categor'));
    echo "Category permissions: " . (empty($categoryPerms) ? 'None' : implode(', ', $categoryPerms)) . "\n";
    echo "Total permissions: " . count($permissions) . "\n";
} else {
    echo "❌ No cashier user found\n";
}

// Check Manager
echo "\n\nMANAGER ROLE:\n";
echo str_repeat('-', 50) . "\n";
$manager = User::role('manager')->first();
if ($manager) {
    $permissions = $manager->getAllPermissions()->pluck('name')->toArray();
    $categoryPerms = array_filter($permissions, fn($p) => str_contains($p, 'categor'));
    echo "Category permissions: " . (empty($categoryPerms) ? 'None' : implode(', ', $categoryPerms)) . "\n";
    echo "Total permissions: " . count($permissions) . "\n";
} else {
    echo "❌ No manager user found\n";
}

// Check Admin
echo "\n\nADMIN ROLE:\n";
echo str_repeat('-', 50) . "\n";
$admin = User::role('admin')->first();
if ($admin) {
    $permissions = $admin->getAllPermissions()->pluck('name')->toArray();
    $categoryPerms = array_filter($permissions, fn($p) => str_contains($p, 'categor'));
    echo "Category permissions: " . (empty($categoryPerms) ? 'None' : implode(', ', $categoryPerms)) . "\n";
    echo "Total permissions: " . count($permissions) . "\n";
} else {
    echo "❌ No admin user found\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "✓ Verification complete!\n";
