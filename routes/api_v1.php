<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\QuickCheckoutController;
use App\Http\Controllers\API\ReceiptController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\StockMovementController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api/v1 and use the 'api' middleware group.
| Add your application routes below, organized by feature/resource.
|
*/

// ========== PUBLIC ROUTES (no authentication required) ==========

// ========== HEALTH CHECK ENDPOINTS ==========
Route::prefix('health')->group(function () {
    Route::get('/', [HealthController::class, 'check']); // Basic health check
    Route::get('detailed', [HealthController::class, 'detailed']); // Detailed metrics
    Route::get('live', [HealthController::class, 'live']); // Liveness probe
    Route::get('ready', [HealthController::class, 'ready']); // Readiness probe
});

// ========== VERSION ENDPOINTS ==========
Route::prefix('version')->group(function () {
    Route::get('/', [VersionController::class, 'current']); // Current version
    Route::get('detailed', [VersionController::class, 'detailed']); // Detailed version info
    Route::get('compatibility', [VersionController::class, 'checkCompatibility']); // Check client compatibility
    Route::get('changelog', [VersionController::class, 'changelog']); // Release notes
});

// Authentication endpoints
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ========== PROTECTED ROUTES (authentication required) ==========

Route::middleware(['auth:sanctum'])->group(function () {
    // Authentication endpoints
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Example: authenticated user info (legacy endpoint)
    Route::get('user', function (Request $request) {
        return response()->json($request->user());
    });

    // ========== USER MANAGEMENT (Manager/Admin only) ==========
    Route::middleware(['role:manager,admin', 'protect_sensitive'])->prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'store']); // Create cashier
        Route::get('/', [UserController::class, 'index']); // List users
        Route::get('{user}', [UserController::class, 'show']); // Get user
        Route::post('{user}/activate', [UserController::class, 'activate']); // Activate
        Route::post('{user}/deactivate', [UserController::class, 'deactivate']); // Deactivate
        Route::delete('{user}', [UserController::class, 'destroy']); // Delete
    });

    // ========== BRANCH MANAGEMENT (Manager/Admin only) ==========
    Route::middleware(['role:manager,admin', 'protect_sensitive'])->prefix('branches')->group(function () {
        Route::post('/', [BranchController::class, 'store']); // Create branch
        Route::get('/', [BranchController::class, 'index']); // List branches
        Route::get('{branch}', [BranchController::class, 'show']); // Get branch
        Route::put('{branch}', [BranchController::class, 'update']); // Update branch
        Route::delete('{branch}', [BranchController::class, 'destroy']); // Delete branch
    });

    // ========== CATEGORY MANAGEMENT (Manager/Admin for create/update/delete, Cashier+ for view) ==========
    Route::prefix('categories')->group(function () {
        // Cashier+ can view categories
        Route::middleware(['role:cashier,manager,admin'])->group(function () {
            Route::get('/', [CategoryController::class, 'index']); // List categories
            Route::get('{category}', [CategoryController::class, 'show']); // Get category details
        });

        // Manager/Admin can create, update, delete
        Route::middleware(['role:manager,admin', 'protect_sensitive'])->group(function () {
            Route::post('/', [CategoryController::class, 'store']); // Create category
            Route::put('{category}', [CategoryController::class, 'update']); // Update category
            Route::delete('{category}', [CategoryController::class, 'destroy']); // Delete category
        });
    });

    // ========== PRODUCT MANAGEMENT (Manager/Admin for create/update/delete, Cashier+ for view) ==========
    Route::prefix('products')->group(function () {
        // Cashier+ can view products
        Route::middleware(['role:cashier,manager,admin'])->group(function () {
            Route::get('/', [ProductController::class, 'index']); // List products (supports search, barcode, filters)
            Route::get('{product}', [ProductController::class, 'show']); // Get product details

            // ========== KEYBOARD-OPTIMIZED ENDPOINTS ==========
            Route::get('barcode/{barcode}', [ProductController::class, 'searchByBarcode']); // Fast barcode lookup (~300 bytes)
            Route::get('search/quick', [ProductController::class, 'quickSearch']); // Quick product search for POS (~500 bytes max)
        });

        // Manager/Admin can create, update, delete and adjust stock
        Route::middleware(['role:manager,admin', 'protect_sensitive'])->group(function () {
            Route::post('/', [ProductController::class, 'store']); // Create product
            Route::put('{product}', [ProductController::class, 'update']); // Update product
            Route::delete('{product}', [ProductController::class, 'destroy']); // Delete product
            Route::post('{product}/adjust-stock', [ProductController::class, 'adjustStock']); // Adjust stock
        });
    });

    // ========== QUICK CHECKOUT API (Single-call atomic checkout) ==========
    Route::middleware(['role:cashier,manager,admin'])->group(function () {
        Route::post('quick-checkout', [QuickCheckoutController::class, 'completeSale']); // Complete sale in one call
        Route::post('quick-checkout/batch', [QuickCheckoutController::class, 'batchCompleteSales']); // Batch for offline sync
    });

    // Orders routes (placeholder)
    Route::prefix('orders')->group(function () {
        // Cashier+ can view and create orders
        Route::middleware(['role:cashier,manager,admin'])->group(function () {
            Route::post('/', [OrderController::class, 'store']); // Create new order
            Route::get('/', [OrderController::class, 'index']); // List orders
            Route::get('{order}', [OrderController::class, 'show']); // Get order details
            Route::post('{order}/items', [OrderController::class, 'addItem']); // Add item to order
            Route::delete('{order}/items/{item}', [OrderController::class, 'removeItem']); // Remove item from order
            Route::post('{order}/complete', [OrderController::class, 'complete']); // Complete order
            Route::post('{order}/cancel', [OrderController::class, 'cancel']); // Cancel order

            // ========== KEYBOARD-OPTIMIZED ENDPOINTS ==========
            Route::post('{order}/add-item', [OrderController::class, 'quickAddItem']); // Fast item add (~200 bytes response)
            Route::get('{order}/summary', [OrderController::class, 'getSummary']); // Minimal summary (~300 bytes)
            Route::post('{order}/quick-pay', [OrderController::class, 'quickPay']); // Fast payment (~150 bytes response)

            // ========== PAYMENT ROUTES ==========
            Route::post('{order}/payments', [PaymentController::class, 'recordPayment']); // Record payment
            Route::get('{order}/payments', [PaymentController::class, 'getPaymentHistory']); // Get payment history
            Route::get('{order}/payments/summary', [PaymentController::class, 'getPaymentSummary']); // Get payment summary

            // Manager/Admin only - refunds
            Route::middleware(['role:manager,admin', 'protect_sensitive'])->group(function () {
                Route::post('{order}/payments/refund', [PaymentController::class, 'refundPayment']); // Refund payment
            });
        });
    });

    // ========== NEW PAYMENT API ENDPOINTS (Idempotency-based) ==========
    Route::middleware(['role:cashier,manager,admin'])->prefix('payments')->group(function () {
        Route::post('submit', [PaymentController::class, 'submit']); // Submit payment with idempotency key
        Route::get('balance/{orderId}', [PaymentController::class, 'getBalance']); // Get payment balance
        Route::get('{paymentId}/status', [PaymentController::class, 'getStatus']); // Confirm payment status
        Route::get('history/{orderId}', [PaymentController::class, 'getHistory']); // Get payment history

        // ========== RECEIPT & INVOICE ROUTES ==========
        Route::get('{order}/receipt', [ReceiptController::class, 'show']); // Get receipt data (JSON)
        Route::get('{order}/receipt/text', [ReceiptController::class, 'text']); // Get receipt as plain text
        Route::get('{order}/receipt/html', [ReceiptController::class, 'html']); // Get receipt as HTML
        Route::post('{order}/receipt/reprint', [ReceiptController::class, 'reprint']); // Reprint receipt

        // Manager/Admin only - invoices
        Route::middleware(['role:manager,admin'])->group(function () {
            Route::get('{order}/invoice', [ReceiptController::class, 'invoice']); // Get invoice data
            Route::get('{order}/invoice/csv', [ReceiptController::class, 'downloadInvoiceCsv']); // Download as CSV
            Route::get('{order}/invoice/json', [ReceiptController::class, 'downloadInvoiceJson']); // Download as JSON
        });

    });

    // ========== STOCK MOVEMENT TRACKING (Cashier+ for view) ==========
    Route::middleware(['role:cashier,manager,admin'])->prefix('stock-movements')->group(function () {
        Route::get('/', [StockMovementController::class, 'index']); // List movements with filters
        Route::get('{movement}', [StockMovementController::class, 'show']); // Get movement details
        Route::get('summary', [StockMovementController::class, 'summary']); // Get movement summary
        Route::get('products/{product}/history', [StockMovementController::class, 'productHistory']); // Product movement history
    });

    // ========== REPORTS (Cashier+ for sales, Manager/Admin for profit) ==========
    Route::middleware(['role:cashier,manager,admin'])->prefix('reports')->group(function () {
        // All cashiers can view sales reports for their branch
        Route::get('daily-sales', [ReportController::class, 'dailySales']); // Daily sales by date
        Route::get('cashier-sales', [ReportController::class, 'cashierSales']); // Sales by cashier
        Route::get('product-sales', [ReportController::class, 'productSales']); // Sales by product

        // Managers and admins can view profit reports
        Route::middleware(['role:manager,admin'])->group(function () {
            Route::get('profit', [ReportController::class, 'profitReport']); // Profit analysis
        });
    });
});

