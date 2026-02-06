# POS System API Documentation (All Endpoints)

**Generated:** 2026-02-01  
**Scope:** All routes defined in routes/api.php and routes/api_v1.php

---

## Base URL
```
/api/v1
```

## Authentication
- Protected routes require **Sanctum** token:
  - `Authorization: Bearer {token}`
- Role-based access enforced per route (cashier/manager/admin).

## Standard Response Format
**Success**
```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

**Error**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

---

# Public Endpoints

## Health

### 1) Health Check
- **Method:** GET
- **URL:** `/api/v1/health`
- **Description:** Basic health status for API, database, and cache.
- **Request Body:** None
- **Response (200/503):**
```json
{
  "status": "healthy",
  "timestamp": "2026-02-01T10:00:00Z",
  "api": "operational",
  "version": "1.0.0",
  "database": "operational",
  "cache": "operational"
}
```

### 2) Detailed Health
- **Method:** GET
- **URL:** `/api/v1/health/detailed`
- **Description:** Detailed health with service checks and memory usage.
- **Request Body:** None
- **Response (200/503):**
```json
{
  "status": "healthy",
  "timestamp": "2026-02-01T10:00:00Z",
  "api": {
    "version": "1.0.0",
    "environment": "production",
    "debug": false
  },
  "services": {
    "database": "operational",
    "cache": "operational"
  },
  "resources": {
    "memory_usage_mb": 64.5,
    "memory_limit_mb": "512"
  }
}
```

### 3) Liveness Probe
- **Method:** GET
- **URL:** `/api/v1/health/live`
- **Description:** Liveness probe for container health.
- **Request Body:** None
- **Response (200):**
```json
{ "alive": true }
```

### 4) Readiness Probe
- **Method:** GET
- **URL:** `/api/v1/health/ready`
- **Description:** Readiness probe (checks critical dependencies).
- **Request Body:** None
- **Response (200/503):**
```json
{ "ready": true }
```

---

## Version

### 5) Current Version
- **Method:** GET
- **URL:** `/api/v1/version`
- **Description:** Basic API version and environment info.
- **Request Body:** None
- **Response (200):**
```json
{
  "api": "1.0.0",
  "name": "POS System",
  "environment": "production",
  "timestamp": "2026-02-01T10:00:00Z"
}
```

### 6) Detailed Version
- **Method:** GET
- **URL:** `/api/v1/version/detailed`
- **Description:** Full build, framework, and database version info.
- **Request Body:** None
- **Response (200):**
```json
{
  "api": {
    "version": "1.0.0",
    "name": "POS System",
    "release_date": "2026-01-28",
    "build": "dev"
  },
  "framework": { "laravel": "10.x", "php": "8.x" },
  "database": { "version": "15.x", "driver": "pgsql" },
  "features": {
    "api_v1": true,
    "websockets": false,
    "authentication": "sanctum",
    "rate_limiting": true,
    "audit_logging": true
  },
  "supported_clients": {
    "electron": ">=1.0.0",
    "web": ">=1.0.0",
    "mobile": ">=1.0.0"
  },
  "endpoints": { "api": "/api/v1", "health": "/health", "version": "/version" },
  "timestamp": "2026-02-01T10:00:00Z"
}
```

### 7) Compatibility Check
- **Method:** GET
- **URL:** `/api/v1/version/compatibility`
- **Description:** Check client compatibility.
- **Query Params:** `client` (electron|web|mobile), `version` (e.g., 1.0.0)
- **Request Body:** None
- **Response (200/426/400):**
```json
{
  "compatible": true,
  "client": "electron",
  "client_version": "1.0.0",
  "required_version": "1.0.0",
  "api_version": "1.0.0",
  "timestamp": "2026-02-01T10:00:00Z",
  "message": "Compatible",
  "upgrade_required": false
}
```

### 8) Changelog
- **Method:** GET
- **URL:** `/api/v1/version/changelog`
- **Description:** Release notes.
- **Query Params:** `limit` (default 10), `version` (optional)
- **Request Body:** None
- **Response (200/404):**
```json
{
  "releases": [
    {
      "version": "1.0.0",
      "release_date": "2026-01-28",
      "status": "current",
      "changes": {
        "features": ["Complete POS API implementation"],
        "bugfixes": ["CORS configuration for desktop apps"],
        "security": ["API rate limiting"]
      }
    }
  ],
  "total": 1,
  "limit": 10
}
```

---

## API Authentication

### 9) Login
- **Method:** POST
- **URL:** `/api/v1/auth/login`
- **Description:** Authenticate and obtain API token.
- **Request Body:**
```json
{ "email": "user@example.com", "password": "secret" }
```
- **Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "plain-text-token",
    "user": { "id": 1, "name": "John Doe", "email": "user@example.com" }
  }
}
```

---

# Protected Endpoints (Auth Required)

## Auth

### 10) Logout
- **Method:** POST
- **URL:** `/api/v1/auth/logout`
- **Description:** Revoke current user tokens.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Logged out successfully" }
```

### 11) Current User
- **Method:** GET
- **URL:** `/api/v1/auth/me`
- **Description:** Get authenticated user profile.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "email_verified_at": "2026-01-01T10:00:00Z",
    "created_at": "2026-01-01T10:00:00Z"
  }
}
```

### 12) Legacy User Info
- **Method:** GET
- **URL:** `/api/v1/user`
- **Description:** Legacy user endpoint returning raw user model JSON.
- **Request Body:** None
- **Response (200):**
```json
{ "id": 1, "name": "John Doe", "email": "user@example.com" }
```

---

## Users (Manager/Admin Only)

### 13) Create Cashier
- **Method:** POST
- **URL:** `/api/v1/users`
- **Description:** Create new cashier user.
- **Request Body:**
```json
{ "name": "Jane Cashier", "email": "jane@example.com", "password": "P@ssw0rd1" }
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Cashier created successfully",
  "data": {
    "id": 10,
    "name": "Jane Cashier",
    "email": "jane@example.com",
    "is_active": true,
    "roles": ["cashier"]
  }
}
```

### 14) List Users
- **Method:** GET
- **URL:** `/api/v1/users`
- **Description:** List users with filters and pagination.
- **Query Params:** `is_active`, `role`, `search`, `per_page`
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": {
    "data": [ { "id": 1, "name": "John", "email": "john@example.com" } ],
    "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
  }
}
```

### 15) Get User
- **Method:** GET
- **URL:** `/api/v1/users/{user}`
- **Description:** Get user details.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "name": "John",
    "email": "john@example.com",
    "is_active": true,
    "roles": ["cashier"],
    "permissions": ["create_order"],
    "created_at": "2026-01-01T10:00:00Z",
    "updated_at": "2026-01-10T10:00:00Z"
  }
}
```

### 16) Activate User
- **Method:** POST
- **URL:** `/api/v1/users/{user}/activate`
- **Description:** Activate a user account.
- **Request Body:**
```json
{ "is_active": true }
```
- **Response (200):**
```json
{
  "success": true,
  "message": "User activated successfully",
  "data": { "id": 1, "name": "John", "is_active": true }
}
```

### 17) Deactivate User
- **Method:** POST
- **URL:** `/api/v1/users/{user}/deactivate`
- **Description:** Deactivate a user account.
- **Request Body:**
```json
{ "is_active": false }
```
- **Response (200):**
```json
{
  "success": true,
  "message": "User deactivated successfully",
  "data": { "id": 1, "name": "John", "is_active": false }
}
```

### 18) Delete User
- **Method:** DELETE
- **URL:** `/api/v1/users/{user}`
- **Description:** Soft delete a user (deactivate + revoke tokens).
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "User deleted successfully" }
```

---

## Branches (Manager/Admin Only)

### 19) Create Branch
- **Method:** POST
- **URL:** `/api/v1/branches`
- **Description:** Create a new branch.
- **Request Body:**
```json
{
  "name": "Main Branch",
  "code": "MAIN",
  "address": "123 Main St",
  "city": "Springfield",
  "state": "IL",
  "phone": "555-0101",
  "email": "main@example.com",
  "is_active": true
}
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Branch created successfully",
  "data": { "id": 1, "name": "Main Branch", "code": "MAIN", "is_active": true }
}
```

### 20) List Branches
- **Method:** GET
- **URL:** `/api/v1/branches`
- **Description:** List branches with filters and pagination.
- **Query Params:** `is_active`, `search`, `sort_by`, `sort_order`, `per_page`
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Branches retrieved successfully",
  "data": {
    "data": [ { "id": 1, "name": "Main Branch", "code": "MAIN" } ],
    "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
  }
}
```

### 21) Get Branch
- **Method:** GET
- **URL:** `/api/v1/branches/{branch}`
- **Description:** Get branch details.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Branch retrieved successfully",
  "data": {
    "id": 1,
    "name": "Main Branch",
    "code": "MAIN",
    "address": "123 Main St",
    "city": "Springfield",
    "state": "IL",
    "phone": "555-0101",
    "email": "main@example.com",
    "is_active": true,
    "user_count": 3,
    "users": [ { "id": 1, "name": "John", "email": "john@example.com" } ]
  }
}
```

### 22) Update Branch
- **Method:** PUT
- **URL:** `/api/v1/branches/{branch}`
- **Description:** Update branch details.
- **Request Body (any fields):**
```json
{ "name": "Main Branch", "code": "MAIN", "is_active": true }
```
- **Response (200):**
```json
{ "success": true, "message": "Branch updated successfully", "data": { "id": 1 } }
```

### 23) Delete Branch
- **Method:** DELETE
- **URL:** `/api/v1/branches/{branch}`
- **Description:** Delete branch (fails if active users exist).
- **Request Body:** None
- **Response (200/422):**
```json
{ "success": true, "message": "Branch deleted successfully", "data": { "id": 1 } }
```

---

## Categories

### 24) List Categories
- **Method:** GET
- **URL:** `/api/v1/categories`
- **Description:** List all product categories.
- **Access:** Cashier+
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Beverages",
      "description": "All drink items",
      "created_at": "2026-02-01T10:00:00Z",
      "updated_at": "2026-02-01T10:00:00Z"
    },
    {
      "id": 2,
      "name": "Snacks",
      "description": "Food items and snacks",
      "created_at": "2026-02-01T10:00:00Z",
      "updated_at": "2026-02-01T10:00:00Z"
    }
  ]
}
```

### 25) Get Category
- **Method:** GET
- **URL:** `/api/v1/categories/{category}`
- **Description:** Get category details.
- **Access:** Cashier+
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Category retrieved successfully",
  "data": {
    "id": 1,
    "name": "Beverages",
    "description": "All drink items",
    "created_at": "2026-02-01T10:00:00Z",
    "updated_at": "2026-02-01T10:00:00Z"
  }
}
```

### 26) Create Category
- **Method:** POST
- **URL:** `/api/v1/categories`
- **Description:** Create a new product category.
- **Access:** Manager/Admin
- **Request Body:**
```json
{
  "name": "Beverages",
  "description": "All drink items"
}
```
- **Validation:**
  - `name` - Required, string, max 255 chars, unique
  - `description` - Optional, string
- **Response (201):**
```json
{
  "success": true,
  "message": "Category created successfully",
  "data": {
    "id": 1,
    "name": "Beverages",
    "description": "All drink items",
    "created_at": "2026-02-01T10:00:00Z"
  }
}
```
- **Error (422):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "name": ["A category with this name already exists."]
  }
}
```

### 27) Update Category
- **Method:** PUT
- **URL:** `/api/v1/categories/{category}`
- **Description:** Update category details.
- **Access:** Manager/Admin
- **Request Body:**
```json
{
  "name": "Beverages",
  "description": "Updated description"
}
```
- **Validation:** Same as create (name remains unique per category)
- **Response (200):**
```json
{
  "success": true,
  "message": "Category updated successfully",
  "data": {
    "id": 1,
    "name": "Beverages",
    "description": "Updated description",
    "updated_at": "2026-02-01T10:00:00Z"
  }
}
```

### 28) Delete Category
- **Method:** DELETE
- **URL:** `/api/v1/categories/{category}`
- **Description:** Delete a product category. Products with this category will have category_id set to null.
- **Access:** Manager/Admin
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Category deleted successfully",
  "data": { "id": 1 }
}
```
- **Error (404):**
```json
{
  "success": false,
  "message": "Resource not found"
}
```

---

## Products

### 29) List Products
- **Method:** GET
- **URL:** `/api/v1/products`
- **Description:** List products with filters/search and pagination.
- **Query Params:** `branch_id`, `is_active`, `category`, `search`, `barcode`, `low_stock`, `sort_by`, `sort_order`, `per_page`
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Products retrieved successfully",
  "data": {
    "data": [ { "id": 1, "sku": "SKU-001", "name": "Cola", "price": 2.5 } ],
    "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
  }
}
```

### 30) Get Product
- **Method:** GET
- **URL:** `/api/v1/products/{product}`
- **Description:** Get product details.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "branch_id": 1,
    "sku": "SKU-001",
    "name": "Cola",
    "description": "1L bottle",
    "price": 2.50,
    "cost": 1.20,
    "profit_margin": 1.30,
    "stock_qty": 20,
    "reorder_level": 5,
    "is_low_stock": false,
    "category": "Beverages",
    "category_id": 1,
    "is_active": true,
    "created_at": "2026-02-01T10:00:00Z",
    "updated_at": "2026-02-01T10:00:00Z"
  }
}
```

### 31) Create Product
- **Method:** POST
- **URL:** `/api/v1/products`
- **Description:** Create a new product.
- **Request Body:**
```json
{
  "branch_id": 1,
  "sku": "SKU-001",
  "name": "Cola",
  "description": "1L bottle",
  "price": 2.50,
  "cost": 1.20,
  "stock_qty": 20,
  "reorder_level": 5,
  "category": "Beverages",
  "is_active": true
}
```
- **Response (201):**
```json
{ "success": true, "message": "Product created successfully", "data": { "id": 1, "sku": "SKU-001" } }
```

### 32) Update Product
- **Method:** PUT
- **URL:** `/api/v1/products/{product}`
- **Description:** Update product details.
- **Request Body (any fields):**
```json
{ "price": 2.75, "stock_qty": 25, "is_active": true }
```
- **Response (200):**
```json
{ "success": true, "message": "Product updated successfully", "data": { "id": 1 } }
```

### 33) Delete Product
- **Method:** DELETE
- **URL:** `/api/v1/products/{product}`
- **Description:** Delete product.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Product deleted successfully", "data": { "id": 1 } }
```

### 34) Adjust Stock
- **Method:** POST
- **URL:** `/api/v1/products/{product}/adjust-stock`
- **Description:** Adjust stock quantity (positive or negative).
- **Request Body:**
```json
{ "quantity": -5, "reason": "Damaged items" }
```
- **Response (200):**
```json
{
  "success": true,
  "message": "Stock adjusted successfully",
  "data": {
    "id": 1,
    "previous_stock": 20,
    "quantity_changed": -5,
    "current_stock": 15,
    "reason": "Damaged items"
  }
}
```

### 35) Barcode Lookup (Keyboard Optimized)
- **Method:** GET
- **URL:** `/api/v1/products/barcode/{barcode}`
- **Description:** Fast barcode/SKU lookup.
- **Request Body:** None
- **Response (200/404):**
```json
{
  "success": true,
  "message": "Product found",
  "data": { "id": 1, "sku": "SKU-001", "name": "Cola", "price": 2.5, "stock": 20, "available": true }
}
```

### 36) Quick Search (Keyboard Optimized)
- **Method:** GET
- **URL:** `/api/v1/products/search/quick`
- **Description:** Quick search for POS screen.
- **Query Params:** `q`
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Products found",
  "data": [ { "id": 1, "sku": "SKU-001", "name": "Cola", "price": 2.5, "stock": 20 } ]
}
```

---

## Orders

### 32) Create Order
- **Method:** POST
- **URL:** `/api/v1/orders`
- **Description:** Create a new order (pending state).
- **Request Body:**
```json
{ "branch_id": 1, "discount": 0, "notes": "" }
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": { "id": 100, "order_number": "ORD-001", "status": "pending" }
}
```

### 33) List Orders
- **Method:** GET
- **URL:** `/api/v1/orders`
- **Description:** List orders with filters and pagination.
- **Query Params:** `branch_id`, `status`, `from_date`, `to_date`, `sort_by`, `sort_order`, `per_page`
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "data": {
    "data": [ { "id": 100, "order_number": "ORD-001", "total": 25.5, "status": "pending" } ],
    "pagination": { "total": 1, "per_page": 15, "current_page": 1, "last_page": 1 }
  }
}
```

### 34) Get Order
- **Method:** GET
- **URL:** `/api/v1/orders/{order}`
- **Description:** Get order details with items.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Order retrieved successfully",
  "data": {
    "id": 100,
    "order_number": "ORD-001",
    "items": [ { "id": 1, "product_id": 1, "quantity": 2, "line_total": 5.0 } ],
    "subtotal": 5.0,
    "tax": 0.5,
    "discount": 0,
    "total": 5.5,
    "status": "pending"
  }
}
```

### 35) Add Item
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/items`
- **Description:** Add item(s) to pending order.
- **Request Body:**
```json
{ "product_id": 1, "quantity": 2 }
```
- **Response (200):**
```json
{ "success": true, "message": "Item added successfully", "data": { "id": 100, "item_count": 2, "total": 5.5 } }
```

### 36) Remove Item
- **Method:** DELETE
- **URL:** `/api/v1/orders/{order}/items/{item}`
- **Description:** Remove item from pending order.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Item removed successfully", "data": { "id": 100, "item_count": 1, "total": 3.0 } }
```

### 37) Complete Order
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/complete`
- **Description:** Complete order and reduce stock.
- **Request Body:**
```json
{ "tax_rate": 0.1 }
```
- **Response (200):**
```json
{ "success": true, "message": "Order completed successfully", "data": { "id": 100, "status": "completed" } }
```

### 38) Cancel Order
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/cancel`
- **Description:** Cancel order (restores stock if completed).
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Order cancelled successfully", "data": { "id": 100, "status": "cancelled" } }
```

### 39) Quick Add Item (Keyboard Optimized)
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/add-item`
- **Description:** Fast add for POS keyboard screen.
- **Request Body:**
```json
{ "product_id": 1, "quantity": 1 }
```
- **Response (200):**
```json
{ "success": true, "data": { "subtotal": 5.0, "tax": 0.5, "total": 5.5, "items": 2 } }
```

### 40) Order Summary (Keyboard Optimized)
- **Method:** GET
- **URL:** `/api/v1/orders/{order}/summary`
- **Description:** Minimal order totals.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "data": { "id": 100, "items": 2, "total": 5.5, "balance": 5.5 } }
```

### 41) Quick Pay (Keyboard Optimized)
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/quick-pay`
- **Description:** Fast payment with minimal response.
- **Request Body:**
```json
{ "method": "cash", "amount": 5.5 }
```
- **Response (200):**
```json
{ "success": true, "data": { "paid": 5.5, "balance": 0, "status": "completed", "complete": true } }
```

---

## Quick Checkout (Optimized)

### Quick Checkout (Single)
- **Method:** POST
- **URL:** `/api/v1/quick-checkout`
- **Description:** Complete a sale in a single atomic call (order + items + payment + stock update).
- **Request Body:**
```json
{
  "branch_id": 1,
  "items": [
    { "product_id": 1, "quantity": 2, "price": 5.00 },
    { "product_id": 5, "quantity": 1 }
  ],
  "payment": { "method": "cash", "amount": 15.50, "reference": "CASH-001" },
  "discount": 0,
  "tax_rate": 0.1,
  "notes": "Quick sale"
}
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Sale completed successfully",
  "data": {
    "order_id": 123,
    "order_number": "ORD-1707123456-7890",
    "total": 15.50,
    "paid": 15.50,
    "change": 0,
    "items_count": 2,
    "status": "completed"
  }
}
```

### Quick Checkout (Batch)
- **Method:** POST
- **URL:** `/api/v1/quick-checkout/batch`
- **Description:** Submit multiple sales in a single request (offline sync, max 50).
- **Request Body:**
```json
{
  "sales": [
    {
      "branch_id": 1,
      "items": [ { "product_id": 1, "quantity": 2 } ],
      "payment": { "method": "cash", "amount": 10.00 },
      "tax_rate": 0.1,
      "timestamp": "2026-02-06T10:30:00Z"
    }
  ]
}
```
- **Response (200):**
```json
{
  "success": true,
  "message": "Batch processing completed",
  "data": {
    "total": 1,
    "successful": 1,
    "failed": 0,
    "results": {
      "successful": [ { "index": 0, "data": { "success": true } } ],
      "failed": []
    }
  }
}
```

## Order Payments (Legacy)

### 42) Record Payment
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/payments`
- **Description:** Record a payment for an order.
- **Request Body:**
```json
{ "method": "cash", "amount": 10.0, "reference": "RCPT-123", "notes": "" }
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Payment recorded successfully",
  "data": {
    "payment_id": 1,
    "order_id": 100,
    "amount_paid": 10.0,
    "current_balance": 0,
    "order_status": "completed"
  }
}
```

### 43) Payment History
- **Method:** GET
- **URL:** `/api/v1/orders/{order}/payments`
- **Description:** Get payment history for an order.
- **Request Body:** None
- **Response (200):**
```json
{
  "success": true,
  "message": "Payment history retrieved successfully",
  "data": {
    "order_id": 100,
    "total_paid": 10.0,
    "remaining_balance": 0,
    "payments": [ { "id": 1, "method": "cash", "amount": 10.0 } ]
  }
}
```

### 44) Payment Summary
- **Method:** GET
- **URL:** `/api/v1/orders/{order}/payments/summary`
- **Description:** Summary grouped by payment methods.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Payment summary retrieved successfully", "data": { "order_id": 100, "total_paid": 10.0, "payment_methods": [ { "method": "cash", "count": 1, "total": 10.0 } ] } }
```

### 45) Refund Payment (Manager/Admin)
- **Method:** POST
- **URL:** `/api/v1/orders/{order}/payments/refund`
- **Description:** Refund a payment.
- **Request Body:**
```json
{ "method": "cash", "amount": 5.0, "reference": "REF-1", "notes": "" }
```
- **Response (201):**
```json
{ "success": true, "message": "Refund processed successfully", "data": { "refund_id": 10, "refund_amount": 5.0, "order_status": "refunded" } }
```

---

## Payments (Idempotent API)

### 46) Submit Payment (Idempotent)
- **Method:** POST
- **URL:** `/api/v1/payments/submit`
- **Description:** Idempotent payment submission.
- **Headers:** `Idempotency-Key: {unique-key}`
- **Request Body:**
```json
{ "orderId": "100", "amount": 10.0, "method": "cash", "reference": "RCPT-1", "metadata": { "cashier": "john" } }
```
- **Response (201):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "paymentId": "pay_abc",
    "orderId": "100",
    "amount": 10.0,
    "method": "cash",
    "status": "completed",
    "balanceRemaining": 0,
    "totalPaid": 10.0,
    "orderStatus": "paid"
  }
}
```

### 47) Get Balance
- **Method:** GET
- **URL:** `/api/v1/payments/balance/{orderId}`
- **Description:** Get payment balance and history for an order.
- **Request Body:** None
- **Response (200/404):**
```json
{ "success": true, "message": "Balance retrieved successfully", "data": { "orderId": "100", "totalAmount": 10.0, "totalPaid": 10.0, "balanceRemaining": 0, "status": "paid", "payments": [] } }
```

### 48) Get Payment Status
- **Method:** GET
- **URL:** `/api/v1/payments/{paymentId}/status`
- **Description:** Confirm payment status and order balance.
- **Request Body:** None
- **Response (200/404):**
```json
{ "success": true, "message": "Payment status confirmed", "data": { "paymentId": "pay_abc", "orderId": "100", "paymentStatus": "completed", "balanceRemaining": 0 } }
```

### 49) Payment History (Idempotent API)
- **Method:** GET
- **URL:** `/api/v1/payments/history/{orderId}`
- **Description:** Payment history using the idempotent payment store.
- **Query Params:** `limit` (max 500), `offset`
- **Request Body:** None
- **Response (200/404):**
```json
{ "success": true, "message": "Payment history retrieved successfully", "data": { "orderId": "100", "totalPaid": 10.0, "payments": [ { "paymentId": "pay_abc", "amount": 10.0 } ] } }
```

---

## Receipts & Invoices

> Note: These routes are mounted under `/api/v1/payments`.

### 50) Receipt (JSON)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/receipt`
- **Description:** Get receipt data (JSON).
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Receipt data retrieved", "data": { "header": {}, "order": {}, "items": [], "totals": {}, "payment": {} } }
```

### 51) Receipt (Text)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/receipt/text`
- **Description:** Receipt as plain text for thermal printing.
- **Request Body:** None
- **Response (200):** `text/plain` body

### 52) Receipt (HTML)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/receipt/html`
- **Description:** Receipt as HTML preview.
- **Request Body:** None
- **Response (200):** `text/html` body

### 53) Reprint Receipt
- **Method:** POST
- **URL:** `/api/v1/payments/{order}/receipt/reprint`
- **Description:** Reprint a receipt.
- **Request Body:** `{}`
- **Response (200):**
```json
{ "success": true, "message": "Receipt reprinted", "data": { "header": {}, "order": {}, "items": [], "totals": {}, "payment": {} }, "reprint": true }
```

### 54) Invoice (JSON)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/invoice`
- **Description:** Detailed invoice (manager/admin only).
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Invoice data retrieved", "data": { "invoice": {}, "merchant": {}, "transaction": {}, "items": [], "summary": {} } }
```

### 55) Invoice (CSV)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/invoice/csv`
- **Description:** Download invoice as CSV.
- **Request Body:** None
- **Response (200):** `text/csv` body

### 56) Invoice (JSON Download)
- **Method:** GET
- **URL:** `/api/v1/payments/{order}/invoice/json`
- **Description:** Download invoice as JSON.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Invoice data retrieved", "data": { "invoice": {}, "items": [] } }
```

---

## Stock Movements

### 57) List Movements
- **Method:** GET
- **URL:** `/api/v1/stock-movements`
- **Description:** List stock movements with filters.
- **Query Params:** `type`, `days`, `limit`
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Stock movements retrieved", "data": [ { "id": 1, "type": "sale", "quantity": -2 } ] }
```

### 58) Movement Detail
- **Method:** GET
- **URL:** `/api/v1/stock-movements/{movement}`
- **Description:** Get movement details.
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Stock movement retrieved", "data": { "id": 1, "type": "sale", "quantity": -2, "reference_type": "order" } }
```

### 59) Movement Summary
- **Method:** GET
- **URL:** `/api/v1/stock-movements/summary`
- **Description:** Summary by type and product for a period.
- **Query Params:** `days`, `branch_id` (optional)
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Stock movement summary retrieved", "data": { "period_days": 30, "by_type": {}, "by_product": [] } }
```

### 60) Product Movement History
- **Method:** GET
- **URL:** `/api/v1/stock-movements/products/{product}/history`
- **Description:** Movement history for a specific product.
- **Query Params:** `days`, `limit`
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Product stock history retrieved", "data": { "product": { "id": 1, "sku": "SKU-001" }, "movements": [] } }
```

---

## Reports

### 61) Daily Sales
- **Method:** GET
- **URL:** `/api/v1/reports/daily-sales`
- **Description:** Daily sales report.
- **Query Params:** `start_date`, `end_date`, `branch_id`
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Daily sales report retrieved", "data": { "filter": {}, "summary": {}, "data": [] } }
```

### 62) Cashier Sales
- **Method:** GET
- **URL:** `/api/v1/reports/cashier-sales`
- **Description:** Cashier performance report.
- **Query Params:** `start_date`, `end_date`, `branch_id`
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Cashier sales report retrieved", "data": { "filter": {}, "summary": {}, "data": [] } }
```

### 63) Product Sales
- **Method:** GET
- **URL:** `/api/v1/reports/product-sales`
- **Description:** Product sales report.
- **Query Params:** `start_date`, `end_date`, `branch_id`, `category`
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Product sales report retrieved", "data": { "filter": {}, "summary": {}, "data": [] } }
```

### 64) Profit Report (Manager/Admin)
- **Method:** GET
- **URL:** `/api/v1/reports/profit`
- **Description:** Profit analysis.
- **Query Params:** `start_date`, `end_date`, `branch_id`, `group_by` (date|product)
- **Request Body:** None
- **Response (200):**
```json
{ "success": true, "message": "Profit report retrieved", "data": { "filter": {}, "summary": {}, "data": [] } }
```

---

## Notes
- All request bodies are JSON unless otherwise specified.
- Responses follow the BaseController success/error format unless explicitly returning raw text/HTML/CSV.
- For deeper field-level details, refer to existing technical docs:
  - PAYMENT_API.md
  - ORDER_FLOW_API.md
  - PRODUCT_AND_STOCK_API.md
  - USER_MANAGEMENT_API.md
  - RECEIPT_INVOICE_APIS.md
  - REPORTS_APIS.md
