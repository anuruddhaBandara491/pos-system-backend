# Auth API Fix - Permissions Now Included

## Problem
When logging in with a cashier role, the permissions array was empty in the API response.

## Root Cause
In `AuthController.php`, the login method was using:
```php
'permissions' => $user->permissions->values()
```

This was incorrect because:
1. It tried to access a direct relationship that may not exist
2. It didn't use Spatie's `getAllPermissions()` method which retrieves permissions from roles

## Solution
Updated `AuthController.php` to use Spatie's `getAllPermissions()` method:

```php
// Load roles and get all permissions (direct + role-based)
$user->load('roles');
$permissions = $user->getAllPermissions()->pluck('name')->values();

return $this->success([
    'token' => $token->plainTextToken,
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'branch_id' => $user->branch_id,
        'is_active' => $user->is_active,
        'roles' => $user->roles->pluck('name')->values(),
        'permissions' => $permissions,  // Now returns permission names
    ],
], 'Login successful', 200);
```

## Changes Made

### 1. AuthController::login()
- Changed from `$user->permissions->values()` to `$user->getAllPermissions()->pluck('name')->values()`
- Added `branch_id` and `is_active` to response
- Now properly returns all permissions inherited from roles

### 2. AuthController::me()
- Updated to also return roles and permissions
- Consistent structure with login response

## Test Results

✅ **Cashier role has 8 permissions:**
- view_orders
- create_order
- edit_order
- complete_order
- record_payment
- view_payments
- view_products
- view_stock

✅ **API Response now includes:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "...",
    "user": {
      "id": 3,
      "name": "Test Cashier",
      "email": "cashier@example.com",
      "branch_id": 1,
      "is_active": true,
      "roles": ["cashier"],
      "permissions": [
        "view_orders",
        "create_order",
        "edit_order",
        "complete_order",
        "record_payment",
        "view_payments",
        "view_products",
        "view_stock"
      ]
    }
  }
}
```

## Testing

To test the login endpoint:

```bash
# Using curl (Windows)
curl.exe -X POST "http://localhost/api/v1/auth/login" `
  -H "Content-Type: application/json" `
  -H "Accept: application/json" `
  -d '{"email":"cashier@example.com","password":"password"}'

# Using PowerShell
$body = @{
    email = 'cashier@example.com'
    password = 'password'
} | ConvertTo-Json

$response = Invoke-RestMethod `
    -Uri 'http://localhost/api/v1/auth/login' `
    -Method Post `
    -Body $body `
    -ContentType 'application/json'

$response | ConvertTo-Json -Depth 10
```

## Verification Scripts

Two helper scripts were created for testing:

1. **check-permissions.php** - Verifies roles and permissions in database
2. **test-login-response.php** - Simulates login response structure

Run them with:
```bash
php check-permissions.php
php test-login-response.php
```

## Impact

- ✅ Frontend will now receive proper permissions array
- ✅ RBAC can be properly implemented in Electron app
- ✅ Cashier/Manager/Admin features can be hidden/shown based on permissions
- ✅ Both `/auth/login` and `/auth/me` endpoints return consistent data
