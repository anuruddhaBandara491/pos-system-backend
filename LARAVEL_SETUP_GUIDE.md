# Laravel API-Only POS System Setup Guide
## Complete Implementation Guide

---

## 1. Project Setup & Installation

### 1.1 Create New Laravel Project

```bash
# Option 1: Using Laravel Installer
composer global require laravel/installer
laravel new pos-system --api

# Option 2: Using Composer create-project
composer create-project laravel/laravel pos-system
cd pos-system
```

### 1.2 Initial Configuration

```bash
# Generate application key
php artisan key:generate

# Install required packages
composer require laravel/sanctum laravel/tinker

# Publish Sanctum configuration
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 1.3 Verify Installation

```bash
# Test server
php artisan serve

# Should output: Server running on http://127.0.0.1:8000
```

---

## 2. Database Configuration

### 2.1 Configure .env File

```env
APP_NAME="POS System"
APP_ENV=local
APP_KEY=base64:xxxxx (auto-generated)
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pos_system
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Sanctum (API Authentication)
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:8080,127.0.0.1:3000
SESSION_DOMAIN=localhost

# Electron App Configuration
FRONTEND_URL=http://localhost:3000
BACKEND_URL=http://localhost:8000
```

### 2.2 Configure Database Connection (config/database.php)

```php
'default' => env('DB_CONNECTION', 'pgsql'),

'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 5432),
        'database' => env('DB_DATABASE', 'pos_system'),
        'username' => env('DB_USERNAME', 'postgres'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'schema' => 'public',
        'sslmode' => 'prefer',
    ],
],
```

### 2.3 Create Database (PostgreSQL)

```bash
# On Windows (PowerShell)
psql -U postgres -c "CREATE DATABASE pos_system ENCODING 'UTF8';"

# On Mac/Linux
createdb -U postgres pos_system

# Verify
psql -U postgres -d pos_system -c "\dt"
```

---

## 3. API-Only Configuration

### 3.1 Configure app/Http/Kernel.php

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // Remove web middleware stack - keep only api
    protected $middleware = [
        // Global middleware
        \Illuminate\Foundation\Http\Middleware\HandlePreconditionRequest::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'api' => [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'api.auth' => \App\Http\Middleware\ApiAuthenticate::class,
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'manager' => \App\Http\Middleware\ManagerMiddleware::class,
    ];
}
```

### 3.2 Configure CORS (config/cors.php)

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Total-Count', // For pagination
        'X-Page-Count',
        'X-Per-Page',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

### 3.3 Configure Sanctum (config/sanctum.php)

```php
<?php

return [
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,127.0.0.1,localhost:3000,127.0.0.1:3000'
    )),

    'guard' => ['web'],

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

    'expiration' => null,

    'token_prefix' => 'pos_',
];
```

### 3.4 Modify Routes (routes/api.php)

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Reports\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    
    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('/products/search/barcode/{barcode}', [ProductController::class, 'searchByBarcode']);
    Route::get('/products/branch/{branchId}', [ProductController::class, 'getByBranch']);
    
    // Orders
    Route::apiResource('orders', OrderController::class);
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
    Route::post('/orders/{order}/apply-discount', [OrderController::class, 'applyDiscount']);
    Route::get('/orders/branch/{branchId}', [OrderController::class, 'getByBranch']);
    
    // Stock Management
    Route::apiResource('stock-movements', StockMovementController::class);
    Route::post('/inventory/count', [StockMovementController::class, 'startCount']);
    Route::put('/inventory/count/{count}', [StockMovementController::class, 'updateCount']);
    
    // Reports
    Route::get('/reports/daily-sales', [ReportController::class, 'dailySales']);
    Route::get('/reports/inventory-status', [ReportController::class, 'inventoryStatus']);
    Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
});

// Health check (public)
Route::get('/health', function (Request $request) {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});
```

### 3.5 Remove Web Routes (routes/web.php)

```php
<?php

// Delete entire file or leave empty
// Web routes are not needed for API-only applications
```

### 3.6 Update RouteServiceProvider (app/Providers/RouteServiceProvider.php)

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Remove web routes entirely
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
```

---

## 4. Optimized Folder Structure for Large POS Systems

### 4.1 Directory Structure

```
pos-system/
├── app/
│   ├── Events/               # Domain events
│   │   ├── OrderCreated.php
│   │   ├── StockMovement.php
│   │   └── PaymentProcessed.php
│   │
│   ├── Exceptions/           # Custom exceptions
│   │   ├── ApiException.php
│   │   ├── InsufficientStockException.php
│   │   └── InvalidPaymentException.php
│   │
│   ├── Http/
│   │   ├── Controllers/      # API Controllers (by domain)
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php
│   │   │   ├── Products/
│   │   │   │   ├── ProductController.php
│   │   │   │   └── CategoryController.php
│   │   │   ├── Orders/
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── OrderItemController.php
│   │   │   │   └── PaymentController.php
│   │   │   ├── Stock/
│   │   │   │   ├── StockMovementController.php
│   │   │   │   ├── InventoryController.php
│   │   │   │   └── TransferController.php
│   │   │   ├── Reports/
│   │   │   │   └── ReportController.php
│   │   │   └── Admin/
│   │   │       ├── UserController.php
│   │   │       └── BranchController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── ApiAuthenticate.php
│   │   │   ├── AdminMiddleware.php
│   │   │   ├── ManagerMiddleware.php
│   │   │   ├── ValidateBranchAccess.php
│   │   │   └── OrganizationContext.php
│   │   │
│   │   ├── Requests/        # Form Validation Requests
│   │   │   ├── Auth/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   ├── RegisterRequest.php
│   │   │   │   └── RefreshTokenRequest.php
│   │   │   ├── Orders/
│   │   │   │   ├── StoreOrderRequest.php
│   │   │   │   ├── UpdateOrderRequest.php
│   │   │   │   └── RefundOrderRequest.php
│   │   │   └── Products/
│   │   │       └── StoreProductRequest.php
│   │   │
│   │   └── Resources/       # API Resources (serialization)
│   │       ├── ProductResource.php
│   │       ├── OrderResource.php
│   │       ├── UserResource.php
│   │       └── Collections/
│   │           ├── ProductCollection.php
│   │           └── OrderCollection.php
│   │
│   ├── Models/              # Eloquent Models
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── Branch.php
│   │   ├── Product.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── StockLevel.php
│   │   ├── StockMovement.php
│   │   ├── Payment.php
│   │   └── Customer.php
│   │
│   ├── Services/            # Business Logic (Domain Services)
│   │   ├── OrderService.php
│   │   ├── StockService.php
│   │   ├── PaymentService.php
│   │   ├── InventoryService.php
│   │   ├── ReportService.php
│   │   └── AuthService.php
│   │
│   ├── Repositories/        # Data Access Layer (Repository Pattern)
│   │   ├── Contracts/
│   │   │   ├── OrderRepository.php (interface)
│   │   │   ├── ProductRepository.php (interface)
│   │   │   └── StockRepository.php (interface)
│   │   └── Eloquent/
│   │       ├── EloquentOrderRepository.php
│   │       ├── EloquentProductRepository.php
│   │       └── EloquentStockRepository.php
│   │
│   ├── Observers/           # Model Observers
│   │   ├── OrderObserver.php
│   │   ├── ProductObserver.php
│   │   └── StockMovementObserver.php
│   │
│   ├── Listeners/           # Event Listeners
│   │   ├── SendOrderNotification.php
│   │   ├── UpdateInventory.php
│   │   └── LogAuditTrail.php
│   │
│   ├── Policies/            # Authorization Policies
│   │   ├── OrderPolicy.php
│   │   ├── ProductPolicy.php
│   │   └── UserPolicy.php
│   │
│   ├── Traits/              # Reusable Traits
│   │   ├── ApiResponse.php
│   │   ├── HasOrganization.php
│   │   ├── HasBranch.php
│   │   └── HasUuid.php
│   │
│   ├── Enums/               # Enumeration types
│   │   ├── OrderStatus.php
│   │   ├── PaymentStatus.php
│   │   ├── MovementType.php
│   │   └── UserRole.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── RepositoryServiceProvider.php
│       └── EventServiceProvider.php
│
├── bootstrap/
│   ├── app.php
│   └── cache/
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cors.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── sanctum.php
│   ├── services.php
│   ├── cache.php
│   └── pos.php              # Custom POS configuration
│
├── database/
│   ├── factories/           # Model Factories for Testing
│   │   ├── UserFactory.php
│   │   ├── ProductFactory.php
│   │   ├── OrderFactory.php
│   │   └── CustomerFactory.php
│   │
│   ├── migrations/          # Database Migrations
│   │   ├── 2026_01_01_000001_create_organizations_table.php
│   │   ├── 2026_01_01_000002_create_branches_table.php
│   │   └── ... (38 tables)
│   │
│   ├── seeders/             # Database Seeders
│   │   ├── DatabaseSeeder.php
│   │   ├── OrganizationSeeder.php
│   │   ├── BranchSeeder.php
│   │   ├── ProductSeeder.php
│   │   ├── UserSeeder.php
│   │   └── PaymentMethodSeeder.php
│   │
│   └── schema/              # Database schema documentation
│       └── schema.sql
│
├── routes/
│   ├── api.php              # API routes only
│   └── console.php
│
├── tests/
│   ├── Feature/             # Feature/Integration Tests
│   │   ├── Auth/
│   │   │   └── AuthTest.php
│   │   ├── Orders/
│   │   │   ├── CreateOrderTest.php
│   │   │   ├── RefundOrderTest.php
│   │   │   └── OrderNotFoundTest.php
│   │   └── Stock/
│   │       └── StockMovementTest.php
│   │
│   ├── Unit/                # Unit Tests
│   │   ├── Services/
│   │   │   ├── OrderServiceTest.php
│   │   │   └── StockServiceTest.php
│   │   └── Models/
│   │       ├── OrderTest.php
│   │       └── ProductTest.php
│   │
│   ├── Mocks/               # Mock data for tests
│   │   └── OrderMock.php
│   │
│   └── TestCase.php
│
├── storage/
│   ├── app/
│   ├── framework/
│   └── logs/
│
├── resources/
│   └── lang/                # Localization
│       └── en/
│           ├── messages.php
│           └── errors.php
│
├── .env
├── .env.example
├── .gitignore
├── artisan
├── composer.json
├── composer.lock
├── phpunit.xml
├── README.md
└── docker-compose.yml
```

---

## 5. Environment Setup for Electron

### 5.1 .env Configuration for Electron

```env
# Backend API Configuration
BACKEND_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000,localhost:8080
SESSION_DOMAIN=localhost

# CORS Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080,http://127.0.0.1:3000

# Sanctum Settings
SANCTUM_EXPIRATION=525600  # Token expiration in minutes (1 year)

# API Settings
API_PREFIX=/api/v1
API_RATE_LIMIT=60

# Offline Sync Configuration
OFFLINE_SYNC_ENABLED=true
SYNC_INTERVAL=300  # Seconds between sync attempts

# File uploads (for Electron offline images)
FILESYSTEM_DISK=local
FILE_MAX_SIZE=5242880  # 5MB in bytes
```

### 5.2 Create config/pos.php

```php
<?php

return [
    'app_name' => env('APP_NAME', 'POS System'),
    'api_version' => 'v1',
    'api_prefix' => env('API_PREFIX', '/api/v1'),
    
    // Electron specific settings
    'electron' => [
        'enabled' => true,
        'sync_enabled' => env('OFFLINE_SYNC_ENABLED', true),
        'sync_interval' => env('SYNC_INTERVAL', 300),
        'max_offline_hours' => 24,
    ],
    
    // Rate limiting
    'rate_limit' => [
        'per_minute' => env('API_RATE_LIMIT', 60),
        'burst_limit' => 100,
    ],
    
    // File handling
    'files' => [
        'max_size' => env('FILE_MAX_SIZE', 5242880), // 5MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
    ],
    
    // Transactions
    'transactions' => [
        'idempotency_key_ttl' => 86400, // 24 hours
    ],
];
```

### 5.3 Create docker-compose.yml for Local Development

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:15
    container_name: pos_postgres
    environment:
      POSTGRES_DB: pos_system
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - pos_network

  redis:
    image: redis:7-alpine
    container_name: pos_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - pos_network

  laravel:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: pos_laravel
    ports:
      - "8000:8000"
    environment:
      - DB_HOST=postgres
      - DB_DATABASE=pos_system
      - DB_USERNAME=postgres
      - DB_PASSWORD=postgres
      - REDIS_HOST=redis
    volumes:
      - .:/app
    depends_on:
      - postgres
      - redis
    networks:
      - pos_network
    command: php artisan serve --host=0.0.0.0

volumes:
  postgres_data:
  redis_data:

networks:
  pos_network:
```

### 5.4 Create Dockerfile

```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpq-dev libzip-dev unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files
COPY . .

# Install Laravel dependencies
RUN composer install --no-interaction --prefer-dist

# Generate application key
RUN php artisan key:generate

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0"]
```

---

## 6. Core Service & Model Examples

### 6.1 User Model with Sanctum (app/Models/User.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasOrganization;
use App\Traits\HasBranch;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasOrganization, HasBranch;

    protected $fillable = [
        'organization_id',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'role_id',
        'primary_branch_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function primaryBranch()
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function canAccessBranch($branchId)
    {
        return $this->branches->contains($branchId);
    }

    public function isAdmin()
    {
        return $this->role?->name === 'admin';
    }

    public function isManager()
    {
        return $this->role?->name === 'manager';
    }

    public function isCashier()
    {
        return $this->role?->name === 'cashier';
    }
}
```

### 6.2 Order Model (app/Models/Order.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasOrganization;

class Order extends Model
{
    use HasFactory, HasOrganization;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'order_number',
        'order_type',
        'order_date',
        'order_time',
        'customer_id',
        'cashier_id',
        'subtotal',
        'total_discount',
        'total_tax',
        'total_amount',
        'status',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'order_date' => 'date',
        'order_time' => 'datetime:H:i:s',
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('order_date', $date);
    }

    // Methods
    public function calculateTotal()
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->total_amount = $this->subtotal + $this->total_tax - $this->total_discount;
        return $this;
    }

    public function canBeRefunded()
    {
        return in_array($this->status, ['completed', 'hold']);
    }
}
```

### 6.3 Order Service (app/Services/OrderService.php)

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Exceptions\InsufficientStockException;
use DB;

class OrderService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Create a new order (transaction-safe)
     */
    public function createOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Validate stock availability
            $this->validateStockAvailability($data['items']);

            // Create order
            $order = Order::create([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $data['branch_id'],
                'order_number' => $this->generateOrderNumber(),
                'order_date' => $data['order_date'] ?? today(),
                'order_time' => $data['order_time'] ?? now()->toTimeString(),
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => auth()->id(),
                'status' => 'completed',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            // Create order items
            foreach ($data['items'] as $item) {
                $this->createOrderItem($order, $item);
            }

            // Calculate totals
            $order->calculateTotal()->save();

            // Deduct stock
            $this->deductStockForOrder($order);

            // Record stock movements
            $this->recordStockMovements($order);

            return $order;
        });
    }

    /**
     * Refund an order
     */
    public function refundOrder(Order $order, array $data = [])
    {
        return DB::transaction(function () use ($order, $data) {
            if (!$order->canBeRefunded()) {
                throw new \Exception('Order cannot be refunded');
            }

            // Create reverse stock movements
            foreach ($order->items as $item) {
                $this->stockService->addStock(
                    $item->product_id,
                    $item->quantity,
                    'return',
                    'Order refund: ' . $order->order_number
                );
            }

            // Create refund record
            $order->update(['status' => 'refunded']);

            return $order;
        });
    }

    /**
     * Validate stock availability
     */
    protected function validateStockAvailability(array $items)
    {
        foreach ($items as $item) {
            $stock = StockLevel::where('product_id', $item['product_id'])
                ->where('branch_id', auth()->user()->primary_branch_id)
                ->first();

            if (!$stock || $stock->quantity_available < $item['quantity']) {
                throw new InsufficientStockException(
                    "Insufficient stock for product {$item['product_id']}"
                );
            }
        }
    }

    /**
     * Create order item
     */
    protected function createOrderItem(Order $order, array $data)
    {
        $lineTotal = $data['quantity'] * $data['unit_price'];
        $taxAmount = $lineTotal * ($data['tax_rate'] ?? 0) / 100;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'discount_amount' => $data['discount_amount'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
        ]);
    }

    /**
     * Deduct stock for order
     */
    protected function deductStockForOrder(Order $order)
    {
        foreach ($order->items as $item) {
            $this->stockService->deductStock(
                $item->product_id,
                $item->quantity,
                'order',
                'Order: ' . $order->order_number
            );
        }
    }

    /**
     * Record stock movements
     */
    protected function recordStockMovements(Order $order)
    {
        foreach ($order->items as $item) {
            StockMovement::create([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $order->branch_id,
                'product_id' => $item->product_id,
                'movement_type' => 'out',
                'quantity' => $item->quantity,
                'reason' => 'sale',
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'recorded_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber()
    {
        $prefix = 'ORD-' . date('Ymd');
        $count = Order::where('order_number', 'like', $prefix . '%')
            ->count() + 1;
        return $prefix . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
```

### 6.4 Auth Controller (app/Http/Controllers/Auth/AuthController.php)

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Login user and return token
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->validated())) {
            return $this->error('Invalid credentials', 401);
        }

        $user = Auth::user();
        
        // Create token
        $token = $user->createToken('pos-app', ['*'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get current user
     */
    public function me()
    {
        return $this->success(new UserResource(auth()->user()));
    }

    /**
     * Logout user (revoke token)
     */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        $user = auth()->user();
        
        // Revoke old token
        auth()->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('pos-app', ['*'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
```

---

## 7. Reusable Traits

### 7.1 ApiResponse Trait (app/Traits/ApiResponse.php)

```php
<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return success response
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return error response
     */
    protected function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Return paginated response
     */
    protected function paginated($items, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }
}
```

### 7.2 HasOrganization Trait (app/Traits/HasOrganization.php)

```php
<?php

namespace App\Traits;

use App\Models\Organization;

trait HasOrganization
{
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeByOrganization($query)
    {
        return $query->where('organization_id', auth()->user()->organization_id);
    }
}
```

---

## 8. Middleware for Electron

### 8.1 OrganizationContext Middleware (app/Http/Middleware/OrganizationContext.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OrganizationContext extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            // Set context for current organization
            $organizationId = auth()->user()->organization_id;
            \DB::statement("SET app.organization_id TO '$organizationId'");
        }

        return $next($request);
    }
}
```

### 8.2 ValidateBranchAccess Middleware (app/Http/Middleware/ValidateBranchAccess.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateBranchAccess extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $branchId = $request->route('branch_id') ?? $request->input('branch_id');
        
        if ($branchId && !auth()->user()->canAccessBranch($branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch',
            ], 403);
        }

        return $next($request);
    }
}
```

---

## 9. Testing Setup

### 9.1 Feature Test Example (tests/Feature/Orders/CreateOrderTest.php)

```php
<?php

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Branch;
use App\Models\StockLevel;

class CreateOrderTest extends TestCase
{
    protected $user;
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->branch = Branch::factory()->create();
        $this->user->branches()->attach($this->branch);
    }

    public function test_can_create_order()
    {
        $product = Product::factory()->create();
        
        // Create stock
        StockLevel::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'customer_id' => null,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 5,
                        'unit_price' => 100,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => ['id', 'order_number', 'total_amount'],
        ]);
    }

    public function test_insufficient_stock_error()
    {
        $product = Product::factory()->create();
        
        StockLevel::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'quantity_on_hand' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 5,
                        'unit_price' => 100,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }
}
```

---

## 10. Development Workflow Commands

### 10.1 Essential Artisan Commands

```bash
# Database
php artisan migrate:fresh --seed      # Fresh migration with seeders
php artisan migrate:rollback          # Rollback last migration
php artisan make:migration create_users_table

# Models & Resources
php artisan make:model Product -mr    # Model with Migration and Resource
php artisan make:controller OrderController --api --model=Order
php artisan make:request StoreOrderRequest

# Services & Jobs
php artisan make:service OrderService
php artisan make:job ProcessPayment

# Events & Listeners
php artisan make:event OrderCreated
php artisan make:listener UpdateInventory

# Testing
php artisan test
php artisan test --filter=OrderTest
php artisan test --coverage

# Cache & Queue
php artisan cache:clear
php artisan config:clear
php artisan queue:work

# API Documentation
php artisan scribe:generate            # Generate API documentation
```

### 10.2 Tinker Commands (Testing in CLI)

```bash
php artisan tinker

# Test order creation
$order = App\Models\Order::factory()->create();
$order->load('items', 'payments');

# Test user login
$user = App\Models\User::first();
$token = $user->createToken('pos-app')->plainTextToken;

# Check stock
$stock = App\Models\StockLevel::where('product_id', 1)->first();
$stock->quantity_available;
```

---

## 11. Quick Start Checklist

```
□ Laravel installation
□ Install dependencies (composer install)
□ Configure .env file
□ Generate APP_KEY
□ Create PostgreSQL database
□ Configure Sanctum
□ Configure CORS
□ Remove web routes
□ Create migrations
□ Run migrations (php artisan migrate)
□ Create seeders
□ Create models with relationships
□ Create services
□ Create controllers
□ Create API resources
□ Create form requests
□ Create traits
□ Define routes
□ Setup middleware
□ Configure error handling
□ Write tests
□ Test API endpoints
□ Setup Docker (optional)
□ Deploy configuration
```

---

## 12. Production Considerations

```bash
# Before deployment
APP_DEBUG=false
APP_ENV=production
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Database
php artisan migrate --force

# Monitoring
php artisan queue:work --daemon
php artisan schedule:work
```

---

## Summary

✅ **API-Only Architecture**: No web routes, pure REST API  
✅ **Scalable Structure**: Domain-driven folder organization  
✅ **Electron Ready**: CORS, Sanctum, offline support  
✅ **Production Quality**: Error handling, transactions, testing  
✅ **Developer Friendly**: Clear conventions and patterns  

Your POS API is now ready to be consumed by the Electron desktop frontend!

