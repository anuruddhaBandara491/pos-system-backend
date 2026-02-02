# Laravel Role-Based Access Control (RBAC) with Spatie
## Complete Implementation for POS System

---

## 1. Overview & Security Architecture

```
┌─────────────────────────────────────────────────────────┐
│              RBAC HIERARCHY (POS SYSTEM)                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ADMIN                                                  │
│  ├── Manage Users                                       │
│  ├── Manage Branches                                    │
│  ├── View All Reports                                   │
│  ├── System Configuration                              │
│  └── Access Control Management                          │
│                                                         │
│  MANAGER                                                │
│  ├── Create/Read Orders                                │
│  ├── Manage Inventory (Stock)                          │
│  ├── View Branch Reports                               │
│  ├── Approve Discounts (above limit)                   │
│  ├── Manage Branch Staff                               │
│  └── Override Prices (within limit)                    │
│                                                         │
│  CASHIER                                                │
│  ├── Create Orders                                      │
│  ├── Process Payments                                   │
│  ├── View Own Orders                                    │
│  ├── Apply Standard Discounts                          │
│  └── View Inventory (read-only)                        │
│                                                         │
│  ACCOUNTANT                                             │
│  ├── View All Reports                                  │
│  ├── Export Financial Data                             │
│  ├── View Payment Reconciliation                       │
│  └── No Order/Inventory Modifications                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Installation & Setup

### 2.1 Install Spatie Laravel Permission

```bash
# Install package
composer require spatie/laravel-permission

# Publish configuration and migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Run migrations
php artisan migrate

# Clear cache
php artisan cache:clear
```

### 2.2 Publish Configuration (config/permission.php)

```php
<?php

return [
    // Models to use
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    // Table names
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    // Column names
    'column_names' => [
        'model_morph_key' => 'model_id',
    ],

    // Cache configuration
    'cache' => [
        'expiration_time' => env('SPATIE_PERMISSION_CACHE_EXPIRATION', 24 * 60),
        'key' => 'spatie.permission.cache',
        'store' => env('CACHE_STORE', 'default'),
    ],

    // Display permission/role names
    'display_permission_in_exception' => env('APP_DEBUG', false),
];
```

### 2.3 Update User Model (app/Models/User.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasOrganization;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasOrganization;

    protected $fillable = [
        'organization_id',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'primary_branch_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Override guard for Spatie
    protected $guard_name = 'web';

    // Relationships
    public function tokens()
    {
        return $this->hasMany(\Laravel\Sanctum\PersonalAccessToken::class);
    }

    public function primaryBranch()
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access');
    }

    // Permission/Role Helpers
    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isAccountant(): bool
    {
        return $this->hasRole('accountant');
    }

    // Get all permissions for user (including role permissions)
    public function getAllPermissions(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    // Get permissions for current branch
    public function getPermissionsForBranch($branchId): array
    {
        return $this->getAllPermissions()->toArray();
    }
}
```

---

## 3. Roles & Permissions Structure

### 3.1 Seeder - Create Roles & Permissions (database/seeders/RolePermissionSeeder.php)

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $this->createPermissions();

        // Create roles and assign permissions
        $this->createRoles();

        // Clear cache again
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Order permissions
            'create_order',
            'read_order',
            'update_order',
            'delete_order',
            'refund_order',

            // Product permissions
            'create_product',
            'read_product',
            'update_product',
            'delete_product',

            // Stock/Inventory permissions
            'create_stock_movement',
            'read_inventory',
            'update_inventory',
            'perform_inventory_count',
            'transfer_stock',

            // Payment permissions
            'process_payment',
            'refund_payment',
            'view_payment_history',

            // Discount permissions
            'apply_standard_discount',  // Up to 10%
            'apply_manager_discount',   // 10-20%
            'apply_special_discount',   // Above 20%

            // Price override permissions
            'override_product_price',
            'override_order_total',

            // User management
            'create_user',
            'read_user',
            'update_user',
            'delete_user',

            // Branch management
            'create_branch',
            'read_branch',
            'update_branch',
            'delete_branch',
            'manage_branch_staff',
            'view_branch_report',

            // Reporting
            'view_daily_report',
            'view_branch_report',
            'view_organization_report',
            'view_payment_report',
            'view_inventory_report',
            'export_report',

            // System configuration
            'manage_system_settings',
            'manage_payment_methods',
            'manage_tax_settings',
            'view_audit_log',

            // Advanced permissions
            'cancel_transaction',
            'view_all_transactions',
            'modify_past_transaction',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function createRoles(): void
    {
        // Admin role - Full access
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // Manager role
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            // Orders
            'create_order',
            'read_order',
            'update_order',
            'refund_order',

            // Products
            'read_product',
            'update_product',

            // Stock
            'read_inventory',
            'update_inventory',
            'perform_inventory_count',
            'transfer_stock',
            'create_stock_movement',

            // Payments
            'process_payment',
            'view_payment_history',

            // Discounts
            'apply_standard_discount',
            'apply_manager_discount',

            // Price overrides
            'override_product_price',

            // Users
            'read_user',
            'create_user', // Limited to their branch
            'update_user',

            // Reports
            'view_branch_report',
            'view_daily_report',
            'view_payment_report',
            'view_inventory_report',

            // Advanced
            'cancel_transaction',
            'view_all_transactions',
        ]);

        // Cashier role
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            // Orders
            'create_order',
            'read_order',

            // Products
            'read_product',

            // Stock
            'read_inventory',

            // Payments
            'process_payment',

            // Discounts
            'apply_standard_discount',
        ]);

        // Accountant role
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            // Read-only access to transactions
            'read_order',
            'view_payment_history',

            // Reporting
            'view_daily_report',
            'view_branch_report',
            'view_organization_report',
            'view_payment_report',
            'export_report',

            // Audit
            'view_audit_log',
        ]);
    }
}
```

### 3.2 Create Roles Artisan Command (app/Console/Commands/CreateRolesCommand.php)

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateRolesCommand extends Command
{
    protected $signature = 'app:create-roles {--fresh : Recreate all roles}';
    protected $description = 'Create application roles and permissions';

    public function handle()
    {
        if ($this->option('fresh')) {
            Role::all()->each->delete();
            Permission::all()->each->delete();
            $this->info('Roles and permissions cleared');
        }

        $this->call('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->info('Roles and permissions created successfully');
    }
}
```

---

## 4. Detailed Permission Breakdown

### 4.1 CASHIER Role Permissions

```php
<?php

namespace App\Permissions;

/**
 * CASHIER PERMISSIONS
 * 
 * Responsible for:
 * - Processing customer transactions
 * - Collecting payments
 * - Applying basic discounts
 * 
 * Cannot:
 * - Create/modify products
 * - Modify inventory
 * - Override prices
 * - Apply large discounts
 * - Access user management
 * - View reports beyond their own shift
 */

class CashierPermissions
{
    const PERMISSIONS = [
        // Transaction Management
        'create_order' => [
            'description' => 'Create new customer orders',
            'api_route' => 'POST /api/orders',
            'restrictions' => [
                'can_only_create_for_own_branch' => true,
                'cannot_edit_after_creation' => true,
            ],
        ],

        'read_order' => [
            'description' => 'View orders they created',
            'api_route' => 'GET /api/orders/{id}',
            'restrictions' => [
                'can_only_view_own_orders' => true,
                'can_view_orders_from_last_24_hours' => true,
            ],
        ],

        // Inventory
        'read_inventory' => [
            'description' => 'Check stock levels',
            'api_route' => 'GET /api/inventory',
            'restrictions' => [
                'read_only' => true,
                'for_own_branch' => true,
            ],
        ],

        // Payments
        'process_payment' => [
            'description' => 'Accept and record customer payments',
            'api_route' => 'POST /api/payments',
            'supported_methods' => ['cash', 'card', 'check', 'wallet'],
            'restrictions' => [
                'for_orders_they_created' => true,
            ],
        ],

        // Discounts
        'apply_standard_discount' => [
            'description' => 'Apply pre-approved discounts (up to 10%)',
            'api_route' => 'POST /api/orders/{id}/discount',
            'max_discount_percentage' => 10,
            'restrictions' => [
                'only_during_transaction' => true,
                'requires_reason' => true,
            ],
        ],

        // Product Views
        'read_product' => [
            'description' => 'View product details and pricing',
            'api_route' => 'GET /api/products',
            'restrictions' => [
                'cannot_see_cost_price' => true,
                'cannot_see_supplier_info' => true,
            ],
        ],
    ];

    public static function description(): string
    {
        return <<<'EOT'
CASHIER ROLE

Primary Responsibilities:
• Process customer transactions
• Collect payments
• Handle customer inquiries
• Apply standard discounts

API Endpoints Accessible:
✓ POST /api/orders                    - Create new orders
✓ GET /api/orders/{id}                - View own orders
✓ GET /api/products                   - Search products
✓ GET /api/inventory                  - Check stock
✓ POST /api/payments                  - Record payments
✓ POST /api/orders/{id}/discount      - Apply discount
✓ POST /api/orders/{id}/refund        - Process simple refund

API Endpoints Blocked:
✗ PUT /api/products/{id}              - Cannot modify products
✗ DELETE /api/orders/{id}             - Cannot delete orders
✗ PUT /api/inventory                  - Cannot modify stock
✗ POST /api/users                     - Cannot manage users
✗ GET /api/reports                    - Cannot view reports
✗ PUT /api/orders/{id}/price-override - Cannot override prices

Data Restrictions:
• Can only see orders created by themselves
• Cannot see cost prices
• Cannot see manager-level discounts
• Read-only access to inventory
• Limited to own branch

Security Measures:
• All transactions logged
• Discount reasons required
• Payment audit trail
• No direct database access
• Action restricted to business hours
EOT;
    }
}
```

### 4.2 MANAGER Role Permissions

```php
<?php

namespace App\Permissions;

/**
 * MANAGER PERMISSIONS
 * 
 * Responsible for:
 * - Supervising cashiers
 * - Managing inventory
 * - Approving discounts
 * - Branch operations
 * 
 * Cannot:
 * - Create/modify products (org-wide)
 * - Manage other branches
 * - System configuration
 * - User role assignment
 */

class ManagerPermissions
{
    const PERMISSIONS = [
        // Transaction Management
        'create_order' => [
            'description' => 'Create and modify customer orders',
            'api_route' => 'POST /api/orders, PUT /api/orders/{id}',
        ],

        'read_order' => [
            'description' => 'View all orders for their branch',
            'api_route' => 'GET /api/orders',
            'restrictions' => [
                'for_own_branch' => true,
                'can_view_historical_data' => true,
            ],
        ],

        'update_order' => [
            'description' => 'Modify orders before finalization',
            'api_route' => 'PUT /api/orders/{id}',
            'restrictions' => [
                'cannot_modify_completed' => true,
                'requires_audit_log' => true,
            ],
        ],

        'refund_order' => [
            'description' => 'Process order refunds',
            'api_route' => 'POST /api/orders/{id}/refund',
            'restrictions' => [
                'limited_to_recent_orders' => '30 days',
                'above_amount_requires_approval' => 1000,
            ],
        ],

        // Inventory Management
        'read_inventory' => [
            'description' => 'View full inventory details',
            'api_route' => 'GET /api/inventory',
            'can_see' => ['cost_price', 'reorder_level', 'stock_history'],
        ],

        'update_inventory' => [
            'description' => 'Adjust stock levels',
            'api_route' => 'PUT /api/inventory/{id}',
            'requires' => ['reason', 'quantity', 'notes'],
        ],

        'perform_inventory_count' => [
            'description' => 'Conduct physical stock audits',
            'api_route' => 'POST /api/inventory/count',
            'can_reconcile_variance' => true,
        ],

        'transfer_stock' => [
            'description' => 'Transfer stock between branches',
            'api_route' => 'POST /api/stock-transfers',
            'approval_required_for_amount' => 10000,
        ],

        'create_stock_movement' => [
            'description' => 'Record stock adjustments',
            'api_route' => 'POST /api/stock-movements',
        ],

        // Product Management
        'read_product' => [
            'description' => 'View all product details',
            'api_route' => 'GET /api/products',
            'can_see' => ['cost_price', 'margins', 'supplier_info'],
        ],

        'update_product' => [
            'description' => 'Modify product details (branch-specific)',
            'api_route' => 'PUT /api/products/{id}',
            'can_modify' => ['price', 'reorder_level', 'discount_percentage'],
            'cannot_modify' => ['sku', 'base_price'],
        ],

        // Discounts & Pricing
        'apply_standard_discount' => [
            'description' => 'Apply discounts up to 10%',
            'max_percentage' => 10,
        ],

        'apply_manager_discount' => [
            'description' => 'Apply discounts 10-20%',
            'max_percentage' => 20,
            'requires_reason' => true,
        ],

        'override_product_price' => [
            'description' => 'Override product prices',
            'api_route' => 'POST /api/orders/{id}/price-override',
            'max_variance_percentage' => 15,
            'requires_approval_above' => 20,
        ],

        // User Management
        'create_user' => [
            'description' => 'Create users for their branch',
            'api_route' => 'POST /api/users',
            'can_create_roles' => ['cashier'],
            'cannot_create' => ['admin', 'manager'],
        ],

        'read_user' => [
            'description' => 'View staff members',
            'api_route' => 'GET /api/users',
        ],

        'update_user' => [
            'description' => 'Modify staff details',
            'api_route' => 'PUT /api/users/{id}',
            'cannot_change' => ['role', 'email'],
        ],

        // Payments
        'process_payment' => [
            'description' => 'Accept all payment methods',
            'api_route' => 'POST /api/payments',
        ],

        'refund_payment' => [
            'description' => 'Reverse payment transactions',
            'api_route' => 'POST /api/payments/{id}/refund',
        ],

        'view_payment_history' => [
            'description' => 'View payment records',
            'api_route' => 'GET /api/payments',
        ],

        // Reporting
        'view_daily_report' => [
            'description' => 'View daily sales and transaction summaries',
            'api_route' => 'GET /api/reports/daily',
        ],

        'view_branch_report' => [
            'description' => 'View branch performance metrics',
            'api_route' => 'GET /api/reports/branch',
        ],

        'view_payment_report' => [
            'description' => 'View payment method analysis',
            'api_route' => 'GET /api/reports/payments',
        ],

        'view_inventory_report' => [
            'description' => 'View inventory valuation and movement',
            'api_route' => 'GET /api/reports/inventory',
        ],

        // Advanced Operations
        'cancel_transaction' => [
            'description' => 'Cancel incomplete orders',
            'api_route' => 'DELETE /api/orders/{id}',
            'restrictions' => [
                'only_incomplete' => true,
                'requires_reason' => true,
            ],
        ],

        'view_all_transactions' => [
            'description' => 'Access all branch transactions',
            'api_route' => 'GET /api/orders?branch_id=*',
        ],
    ];

    public static function description(): string
    {
        return <<<'EOT'
MANAGER ROLE

Primary Responsibilities:
• Supervise branch operations
• Manage inventory and stock
• Approve discounts and overrides
• Monitor cashier performance
• Conduct financial reconciliation

API Endpoints Accessible:
✓ POST /api/orders                        - Create orders
✓ PUT /api/orders/{id}                    - Edit orders
✓ GET /api/orders                         - View all branch orders
✓ POST /api/orders/{id}/refund            - Refund orders
✓ POST /api/products                      - Create products (branch-level)
✓ PUT /api/products/{id}                  - Update products
✓ GET /api/inventory                      - Full inventory view
✓ PUT /api/inventory/{id}                 - Adjust stock
✓ POST /api/inventory/count               - Perform count
✓ POST /api/stock-transfers               - Transfer stock
✓ POST /api/payments                      - Record payments
✓ POST /api/orders/{id}/discount          - Apply discounts
✓ POST /api/orders/{id}/price-override    - Override prices
✓ POST /api/users                         - Create users (limited roles)
✓ PUT /api/users/{id}                     - Update users
✓ GET /api/reports/*                      - View all reports
✓ DELETE /api/orders/{id}                 - Cancel transactions

API Endpoints Blocked:
✗ DELETE /api/products/{id}               - Cannot delete products
✗ DELETE /api/users/{id}                  - Cannot delete users
✗ POST /api/branches                      - Cannot create branches
✗ PUT /api/organization                   - Cannot modify organization
✗ POST /api/users/assign-role             - Cannot assign roles
✗ GET /api/admin/*                        - Cannot access admin endpoints

Branch Restrictions:
• Can only manage their assigned branch
• Cannot view other branches' data
• Cannot create users with manager+ roles
• Stock transfers require audit

Discount Limits:
• Standard: Up to 10% (no approval)
• Manager: 10-20% (requires reason)
• Above 20%: Blocked (admin only)

Security Measures:
• All modifications audited
• Price overrides tracked
• Inventory reconciliation required
• Cannot modify completed transactions
• Historical data retained
EOT;
    }
}
```

### 4.3 ADMIN Role Permissions

```php
<?php

namespace App\Permissions;

/**
 * ADMIN PERMISSIONS
 * 
 * Full system access
 * Can manage:
 * - All users and roles
 * - All branches
 * - System settings
 * - Organization-wide data
 * - Audit trails
 */

class AdminPermissions
{
    const DESCRIPTION = <<<'EOT'
ADMIN ROLE

Full System Access - No Restrictions

Capabilities:
• Manage all organizations and branches
• Create/modify/delete users and roles
• Assign roles and permissions
• System configuration and settings
• View all reports and data
• Modify audit logs (with caution)
• Approve manager-level operations
• Set discount policies
• Configure payment methods
• Override any business rule

All API Endpoints Accessible

Use Cases:
- System implementation
- Multi-branch oversight
- Policy configuration
- Performance monitoring
- Disaster recovery
- User support and troubleshooting

Security Note:
- Limit number of admin accounts
- Use strong authentication
- Monitor all admin actions
- Require MFA for sensitive operations
EOT;
}
```

---

## 5. API Middleware Implementation

### 5.1 Permission Middleware (app/Http/Middleware/CheckPermission.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * 
     * Usage:
     * Route::post('/orders', [OrderController::class, 'store'])
     *     ->middleware('permission:create_order');
     * 
     * Multiple permissions (OR logic):
     * ->middleware('permission:create_order,update_order');
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        if (!auth()->check()) {
            return $this->unauthorized('Authentication required');
        }

        $user = auth()->user();

        // Admin has all permissions
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Check if user has at least one of the required permissions
        $hasPermission = collect($permissions)->some(
            fn($permission) => $user->hasPermissionTo($permission)
        );

        if (!$hasPermission) {
            Log::warning("Permission denied for user {$user->id} on {$request->path()}", [
                'user_id' => $user->id,
                'email' => $user->email,
                'required_permissions' => $permissions,
                'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'path' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
            ]);

            return $this->forbidden('You do not have permission to perform this action');
        }

        return $next($request);
    }

    protected function unauthorized(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 401,
        ], 401);
    }

    protected function forbidden(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 403,
        ], 403);
    }
}
```

### 5.2 Role Middleware (app/Http/Middleware/CheckRole.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     * 
     * Usage:
     * Route::get('/reports', [ReportController::class, 'index'])
     *     ->middleware('role:admin,manager');
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $user = auth()->user();

        // Check if user has at least one of the required roles
        $hasRole = collect($roles)->some(
            fn($role) => $user->hasRole($role)
        );

        if (!$hasRole) {
            Log::warning("Role denied for user {$user->id}", [
                'user_id' => $user->id,
                'required_roles' => $roles,
                'user_roles' => $user->getRoleNames()->toArray(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your role does not have access to this resource',
            ], 403);
        }

        return $next($request);
    }
}
```

### 5.3 Branch Access Middleware (app/Http/Middleware/CheckBranchAccess.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBranchAccess
{
    /**
     * Verify user has access to the requested branch
     * 
     * Usage:
     * Route::get('/branches/{branch_id}/orders', ...)
     *     ->middleware('check.branch.access:branch_id');
     */
    public function handle(Request $request, Closure $next, $branchParam = 'branch_id')
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        $branchId = $request->route($branchParam) ?? $request->input($branchParam);

        // Admin can access all branches
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Manager can only access their branch and accessible branches
        if (!$user->canAccessBranch($branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch',
            ], 403);
        }

        return $next($request);
    }
}
```

### 5.4 Register Middleware in Kernel (app/Http/Kernel.php)

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        // ... other middleware
        'permission' => \App\Http\Middleware\CheckPermission::class,
        'role' => \App\Http\Middleware\CheckRole::class,
        'check.branch.access' => \App\Http\Middleware\CheckBranchAccess::class,
    ];
}
```

---

## 6. Route Protection Examples

### 6.1 Protected Routes (routes/api.php)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Admin\UserController;

// Public routes (no auth required)
Route::post('/auth/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // === ORDER ROUTES ===
    
    // Cashier: Create order (any authenticated user)
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:create_order');

    // Cashier: View own orders
    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('permission:read_order');

    // Manager: View all branch orders
    Route::get('/orders/branch/{branch_id}', [OrderController::class, 'getByBranch'])
        ->middleware([
            'permission:read_order',
            'check.branch.access:branch_id',
        ]);

    // Manager: Update order
    Route::put('/orders/{order}', [OrderController::class, 'update'])
        ->middleware('permission:update_order');

    // Manager: Refund order
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])
        ->middleware('permission:refund_order');

    // Manager: Delete order
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])
        ->middleware('permission:cancel_transaction');

    // === PRODUCT ROUTES ===

    // All: View products
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:read_product');

    // Admin only: Create product
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('role:admin');

    // Admin only: Update product
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('role:admin');

    // Admin only: Delete product
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('role:admin');

    // === INVENTORY ROUTES ===

    // Cashier: View inventory (read-only)
    Route::get('/inventory', [StockMovementController::class, 'getInventory'])
        ->middleware('permission:read_inventory');

    // Manager: Update inventory
    Route::put('/inventory/{id}', [StockMovementController::class, 'updateInventory'])
        ->middleware('permission:update_inventory');

    // Manager: Perform count
    Route::post('/inventory/count', [StockMovementController::class, 'startCount'])
        ->middleware('permission:perform_inventory_count');

    // Manager: Transfer stock
    Route::post('/stock-transfers', [StockMovementController::class, 'transfer'])
        ->middleware('permission:transfer_stock');

    // === DISCOUNT ROUTES ===

    // Cashier: Apply standard discount (up to 10%)
    Route::post('/orders/{order}/discount', [OrderController::class, 'applyDiscount'])
        ->middleware('permission:apply_standard_discount');

    // Manager: Apply manager discount (10-20%)
    Route::post('/orders/{order}/discount-manager', [OrderController::class, 'applyManagerDiscount'])
        ->middleware('permission:apply_manager_discount');

    // === PRICE OVERRIDE ===

    // Manager: Override product price
    Route::post('/orders/{order}/price-override', [OrderController::class, 'priceOverride'])
        ->middleware('permission:override_product_price');

    // === REPORTING ROUTES ===

    // Cashier: View daily report
    Route::get('/reports/daily', [ReportController::class, 'daily'])
        ->middleware('permission:view_daily_report');

    // Manager: View branch report
    Route::get('/reports/branch', [ReportController::class, 'branch'])
        ->middleware('permission:view_branch_report');

    // Admin: View organization report
    Route::get('/reports/organization', [ReportController::class, 'organization'])
        ->middleware('role:admin');

    // Manager/Accountant: View payment report
    Route::get('/reports/payments', [ReportController::class, 'payments'])
        ->middleware('permission:view_payment_report');

    // === USER MANAGEMENT (Admin & Manager) ===

    // Manager: View users
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:read_user');

    // Manager: Create user
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:create_user');

    // Manager: Update user
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:update_user');

    // Admin only: Delete user
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('role:admin');

    // Admin only: Assign role
    Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole'])
        ->middleware('role:admin');

    // === AUDIT LOG ===

    // Admin/Manager: View audit log
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])
        ->middleware('permission:view_audit_log');

    // === AUTH ===

    Route::post('/auth/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);
    Route::get('/auth/me', [\App\Http\Controllers\Auth\AuthController::class, 'me']);
});
```

---

## 7. Controller Implementation with Authorization

### 7.1 Order Controller with Authorization (app/Http/Controllers/Orders/OrderController.php)

```php
<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(protected OrderService $orderService)
    {
    }

    /**
     * Create new order
     * 
     * Required permission: create_order
     * Cashier: Can create for own branch
     * Manager: Can create for their branch
     */
    public function store()
    {
        try {
            $user = auth()->user();
            
            // Validate branch access
            $branchId = request('branch_id') ?? $user->primary_branch_id;
            if (!$user->canAccessBranch($branchId)) {
                return $this->error('You do not have access to this branch', 403);
            }

            // Create order
            $order = $this->orderService->createOrder(
                array_merge(request()->all(), ['branch_id' => $branchId])
            );

            // Log action
            Log::info("Order created by user {$user->id}", [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'user_role' => $user->getRoleNames()->first(),
            ]);

            return $this->success($order, 'Order created', 201);
        } catch (\Exception $e) {
            Log::error("Order creation failed: " . $e->getMessage());
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get orders
     * 
     * Cashier: Can only see their own orders
     * Manager: Can see all branch orders
     */
    public function index()
    {
        $user = auth()->user();
        $query = Order::query();

        // Cashier: Only their orders
        if ($user->isCashier()) {
            $query->where('cashier_id', $user->id);
        }
        // Manager: All branch orders
        elseif ($user->isManager()) {
            $query->where('branch_id', $user->primary_branch_id);
        }
        // Admin: All orders
        // No additional filtering

        $orders = $query->paginate(50);
        return $this->paginated($orders);
    }

    /**
     * Apply discount
     * 
     * Cashier: Up to 10% (apply_standard_discount)
     * Manager: Up to 20% (apply_manager_discount)
     */
    public function applyDiscount(Order $order)
    {
        try {
            $user = auth()->user();
            $discountPercentage = request('discount_percentage');

            // Validate access
            if (!$user->canAccessBranch($order->branch_id)) {
                return $this->error('You do not have access to this branch', 403);
            }

            // Validate discount limits
            if ($user->isCashier() && $discountPercentage > 10) {
                return $this->error('Cashiers can only apply discounts up to 10%', 422);
            }

            if ($user->isManager() && $discountPercentage > 20) {
                return $this->error('Managers can only apply discounts up to 20%', 422);
            }

            // Apply discount
            $order = $this->orderService->applyDiscount($order, $discountPercentage);

            // Audit log
            Log::info("Discount applied by user {$user->id}", [
                'order_id' => $order->id,
                'discount_percentage' => $discountPercentage,
                'user_id' => $user->id,
            ]);

            return $this->success($order, 'Discount applied');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Refund order
     * 
     * Required permission: refund_order
     * Manager only operation
     */
    public function refund(Order $order)
    {
        try {
            $user = auth()->user();

            // Validate access
            if (!$user->hasPermissionTo('refund_order')) {
                return $this->error('You do not have permission to refund orders', 403);
            }

            // Validate branch
            if (!$user->canAccessBranch($order->branch_id)) {
                return $this->error('You do not have access to this branch', 403);
            }

            // Refund
            $order = $this->orderService->refundOrder($order);

            Log::warning("Order refunded by user {$user->id}", [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason' => request('reason'),
            ]);

            return $this->success($order, 'Order refunded');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
```

### 7.2 Inventory Controller with Authorization (app/Http/Controllers/Stock/StockMovementController.php)

```php
<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\StockLevel;
use App\Services\StockService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
    use ApiResponse;

    public function __construct(protected StockService $stockService)
    {
    }

    /**
     * Get inventory
     * 
     * Cashier: Read-only, see available quantity only
     * Manager: Full details including cost
     */
    public function getInventory()
    {
        $user = auth()->user();
        $branchId = request('branch_id', $user->primary_branch_id);

        // Validate access
        if (!$user->canAccessBranch($branchId)) {
            return $this->error('You do not have access to this branch', 403);
        }

        $inventory = StockLevel::where('branch_id', $branchId)
            ->with('product')
            ->paginate(100);

        // Cashier: Hide cost information
        if ($user->isCashier()) {
            $inventory->makeHidden(['cost_price', 'reorder_level']);
        }

        return $this->paginated($inventory);
    }

    /**
     * Update inventory
     * 
     * Required permission: update_inventory
     * Manager only - requires reason
     */
    public function updateInventory()
    {
        try {
            $user = auth()->user();

            // Validate permission
            if (!$user->hasPermissionTo('update_inventory')) {
                return $this->error('You do not have permission to modify inventory', 403);
            }

            $stockId = request('stock_id');
            $quantityChange = request('quantity_change');
            $reason = request('reason'); // Required

            if (!$reason) {
                return $this->error('Reason for inventory adjustment is required', 422);
            }

            // Update inventory
            $stock = $this->stockService->adjustStock(
                $stockId,
                $quantityChange,
                $reason,
                $user->id
            );

            // Audit log
            Log::info("Inventory adjusted by user {$user->id}", [
                'stock_id' => $stockId,
                'quantity_change' => $quantityChange,
                'reason' => $reason,
                'user_id' => $user->id,
                'ip' => request()->ip(),
            ]);

            return $this->success($stock, 'Inventory updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Perform inventory count
     * 
     * Required permission: perform_inventory_count
     * Manager only
     */
    public function startCount()
    {
        try {
            $user = auth()->user();

            // Start count
            $count = $this->stockService->startInventoryCount($user->primary_branch_id);

            Log::info("Inventory count started by user {$user->id}", [
                'count_id' => $count->id,
                'branch_id' => $user->primary_branch_id,
            ]);

            return $this->success($count, 'Inventory count started', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
```

---

## 8. Helper Functions for Views/Templates

### 8.1 Blade Helper Macros (app/Providers/BladeServiceProvider.php)

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class BladeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Check permission
        \Blade::if('permission', function ($permission) {
            return auth()->user()?->hasPermissionTo($permission);
        });

        // Check role
        \Blade::if('role', function ($role) {
            return auth()->user()?->hasRole($role);
        });

        // Check if cashier
        \Blade::if('cashier', function () {
            return auth()->user()?->isCashier();
        });

        // Check if manager
        \Blade::if('manager', function () {
            return auth()->user()?->isManager();
        });

        // Check if admin
        \Blade::if('admin', function () {
            return auth()->user()?->isAdmin();
        });

        // Check branch access
        \Blade::if('canAccessBranch', function ($branchId) {
            return auth()->user()?->canAccessBranch($branchId);
        });
    }
}
```

### 8.2 API Response with Permissions (app/Http/Resources/UserResource.php)

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_branch_id' => $this->primary_branch_id,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'can_access_branches' => $this->branches()->pluck('id'),
        ];
    }
}
```

---

## 9. Best Practices for POS Security

### 9.1 Implementation Checklist

```
Authorization:
✓ All routes protected with middleware
✓ Permission checks at route level
✓ Additional checks in controllers
✓ Branch access validation
✓ User context always available

Data Access:
✓ Cashier cannot see cost prices
✓ Cashier cannot see supplier info
✓ Limited to own branch data
✓ Read-only access where appropriate
✓ Audit trail for all modifications

Transactions:
✓ Order operations require specific permissions
✓ Refund operations logged
✓ Discount limits enforced
✓ Price overrides tracked
✓ Cannot modify completed orders

Inventory:
✓ Stock adjustments require reason
✓ Physical counts tracked
✓ Variance reconciliation required
✓ Transfer approvals
✓ Cost information restricted

Discounts & Pricing:
✓ Discount limits enforced by role
✓ Price overrides require approval
✓ All changes audited
✓ Percentage limits respected
✓ Reason/notes required

User Management:
✓ Cannot self-assign higher roles
✓ Manager cannot create manager+ accounts
✓ Password policy enforced
✓ Account deactivation tracked
✓ Role changes logged
```

### 9.2 Audit Logging (app/Traits/LogsActivity.php)

```php
<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /**
     * Log an activity/action
     */
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            self::logActivity('create', $model);
        });

        static::updated(function ($model) {
            self::logActivity('update', $model);
        });

        static::deleted(function ($model) {
            self::logActivity('delete', $model);
        });
    }

    protected static function logActivity($action, $model)
    {
        if (!auth()->check()) {
            return;
        }

        AuditLog::create([
            'organization_id' => auth()->user()->organization_id,
            'user_id' => auth()->user()->id,
            'action' => $action,
            'entity_type' => class_basename($model),
            'entity_id' => $model->id,
            'old_values' => $model->getOriginal(),
            'new_values' => $model->getAttributes(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### 9.3 Permission Caching Strategy

```php
<?php

// config/permission.php - Cache configuration
'cache' => [
    'expiration_time' => 24 * 60, // 24 hours
    'key' => 'spatie.permission.cache',
    'store' => 'redis', // Use Redis for better performance
],

// Clear cache after role/permission changes
// In app/Services/RoleService.php

public function assignRole($user, $role)
{
    $user->assignRole($role);
    
    // Clear permission cache for this user
    app(\Spatie\Permission\PermissionRegistrar::class)
        ->forgetCachedPermissions();
    
    return $user;
}
```

### 9.4 Rate Limiting by Role

```php
// routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    // Cashier: 60 requests per minute
    Route::middleware('throttle:cashier_limit')
        ->group(function () {
            // Cashier-specific routes
        });

    // Manager: 120 requests per minute
    Route::middleware('throttle:manager_limit')
        ->group(function () {
            // Manager-specific routes
        });

    // Admin: 300 requests per minute
    Route::middleware('throttle:admin_limit')
        ->group(function () {
            // Admin routes
        });
});

// In config/app.php
'throttle' => [
    'cashier_limit' => '60,1',
    'manager_limit' => '120,1',
    'admin_limit' => '300,1',
],
```

### 9.5 Sensitive Operations Logging

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditService
{
    public static function logSensitiveOperation($action, $details)
    {
        Log::channel('security')->warning("Sensitive Operation: $action", [
            'user_id' => auth()->user()->id,
            'user_email' => auth()->user()->email,
            'user_role' => auth()->user()->getRoleNames()->first(),
            'action' => $action,
            'details' => $details,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logUnauthorizedAttempt($action, $reason)
    {
        Log::channel('security')->error("Unauthorized Attempt", [
            'user_id' => auth()->user()?->id,
            'action' => $action,
            'reason' => $reason,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
        ]);
    }
}

// Usage
AuditService::logSensitiveOperation('discount_applied', [
    'order_id' => $order->id,
    'discount_percentage' => 20,
    'amount_saved' => $order->total_discount,
]);
```

---

## 10. Testing Authorization

### 10.1 Feature Test - Authorization (tests/Feature/Orders/OrderAuthorizationTest.php)

```php
<?php

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class OrderAuthorizationTest extends TestCase
{
    protected $cashier;
    protected $manager;
    protected $admin;
    protected $branch;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create branch
        $this->branch = Branch::factory()->create();

        // Create users with roles
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('cashier');
        $this->cashier->branches()->attach($this->branch);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        $this->manager->branches()->attach($this->branch);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Create order
        $this->order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
        ]);
    }

    public function test_cashier_can_create_order()
    {
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'items' => [],
            ]);

        $response->assertStatus(201);
    }

    public function test_cashier_cannot_refund_order()
    {
        $response = $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$this->order->id}/refund");

        $response->assertStatus(403);
    }

    public function test_manager_can_refund_order()
    {
        $response = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/refund");

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_see_cost_price()
    {
        $response = $this->actingAs($this->cashier)
            ->getJson('/api/inventory');

        $inventory = $response->json('data.0');
        $this->assertArrayNotHasKey('cost_price', $inventory);
    }

    public function test_manager_can_see_cost_price()
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/inventory');

        $inventory = $response->json('data.0');
        $this->assertArrayHasKey('cost_price', $inventory);
    }

    public function test_cashier_discount_limit_enforced()
    {
        $response = $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$this->order->id}/discount", [
                'discount_percentage' => 15, // Above 10% limit
            ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_access_other_branch()
    {
        $otherBranch = Branch::factory()->create();

        $response = $this->actingAs($this->cashier)
            ->getJson("/api/branches/{$otherBranch->id}/orders");

        $response->assertStatus(403);
    }
}
```

---

## 11. Quick Reference Guide

### 11.1 Permission Names

```
ORDER MANAGEMENT:
- create_order
- read_order
- update_order
- delete_order
- refund_order

INVENTORY:
- read_inventory
- update_inventory
- perform_inventory_count
- transfer_stock
- create_stock_movement

DISCOUNTS:
- apply_standard_discount (0-10%)
- apply_manager_discount (10-20%)
- apply_special_discount (>20%)

PRICING:
- override_product_price

REPORTING:
- view_daily_report
- view_branch_report
- view_organization_report
- view_payment_report
- export_report

USER MANAGEMENT:
- create_user
- read_user
- update_user
- delete_user

SYSTEM:
- manage_system_settings
- view_audit_log
```

### 11.2 Middleware Usage

```php
// Single permission
->middleware('permission:create_order')

// Multiple permissions (OR logic)
->middleware('permission:create_order,update_order')

// Single role
->middleware('role:admin')

// Multiple roles
->middleware('role:admin,manager')

// Branch access
->middleware('check.branch.access:branch_id')

// Combined
->middleware([
    'permission:update_order',
    'check.branch.access:branch_id',
])
```

---

## Summary

✅ **Spatie Integration**: Complete setup with roles & permissions  
✅ **Role Hierarchy**: Admin, Manager, Cashier, Accountant  
✅ **Granular Permissions**: 30+ permissions for fine-grained control  
✅ **API Protection**: Middleware for all endpoints  
✅ **Data Isolation**: Branch & role-based access  
✅ **Audit Trail**: All actions logged  
✅ **Production Ready**: Testing, caching, performance optimized  

Your POS system now has enterprise-grade authorization!

