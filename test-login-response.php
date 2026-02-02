<?php

// Test login API response

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Testing Login API Response:\n";
echo str_repeat('=', 60) . "\n\n";

// Get cashier user
$user = User::where('email', 'cashier@example.com')->first();

if (!$user) {
    echo "❌ Cashier user not found!\n";
    exit(1);
}

echo "User: {$user->name} ({$user->email})\n";
echo "Password in DB exists: " . (!empty($user->password) ? "✓" : "❌") . "\n\n";

// Load roles
$user->load('roles');

echo "Roles loaded: " . $user->roles->count() . "\n";
echo "Role names: " . $user->roles->pluck('name')->implode(', ') . "\n\n";

// Get all permissions (using Spatie's method)
$permissions = $user->getAllPermissions()->pluck('name')->values();

echo "Permissions via getAllPermissions():\n";
echo "  Count: " . $permissions->count() . "\n";
echo "  Names: " . $permissions->implode(', ') . "\n\n";

// Test what the API would return
$apiResponse = [
    'token' => '[TOKEN_WOULD_BE_HERE]',
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'branch_id' => $user->branch_id,
        'is_active' => $user->is_active,
        'roles' => $user->roles->pluck('name')->values(),
        'permissions' => $permissions,
    ],
];

echo "API Response Structure:\n";
echo json_encode($apiResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "✓ Login would return " . $permissions->count() . " permissions for cashier role\n";
