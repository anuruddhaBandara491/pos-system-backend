# POS Backend Architecture & API Overview

**Date:** January 27, 2026  
**Backend:** Laravel 12 REST API  
**Database:** PostgreSQL/MySQL  
**Authentication:** Sanctum Tokens  
**Authorization:** Spatie Permission RBAC  

---

## 1. System Architecture

### Technology Stack
```
┌─────────────────────────────────────────┐
│    Electron Desktop (Keyboard-Driven)   │
│         POS Frontend Screen             │
└──────────────────┬──────────────────────┘
                   │ HTTP/JSON
                   ↓
┌─────────────────────────────────────────┐
│    Laravel 12 REST API Backend          │
│  - Sanctum Authentication               │
│  - Spatie RBAC Authorization            │
│  - Optimized Keyboard Endpoints         │
└──────────────────┬──────────────────────┘
                   │ SQL
                   ↓
┌─────────────────────────────────────────┐
│    PostgreSQL/MySQL Database            │
│  - Normalized Schema                    │
│  - Indexed for Performance              │
└─────────────────────────────────────────┘
```

### API Version
- **Current:** v1 (`/api/v1/`)
- **Prefix:** `/api/v1/`
- **Format:** JSON (all requests/responses)
- **Auth:** Bearer Token (Sanctum)

---

## 2. Database Schema

### Core Tables

#### users
```sql
id | name | email | password | branch_id | is_active | created_at
```
- **Relationships:** HasMany orders (as cashier), BelongsTo branch
- **Indexes:** email (unique), branch_id

#### branches
```sql
id | name | code | address | city | state | phone | email | is_active
```
- **Relationships:** HasMany users, HasMany products, HasMany orders
- **Indexes:** code (unique)

#### products
```sql
id | branch_id | sku | name | description | price | cost | stock_qty | 
reorder_level | category | is_active
```
- **Relationships:** BelongsTo branch, HasMany order_items
- **Indexes:** (branch_id, sku) unique, sku, is_active, category
- **Calculation:** profit_margin = (price - cost) / cost * 100

#### orders
```sql
id | branch_id | cashier_id | order_number | subtotal | tax | 
discount | total | paid_amount | remaining_balance | status | notes
```
- **Relationships:** BelongsTo branch, BelongsTo cashier (user), HasMany items, HasMany payments
- **Indexes:** order_number (unique), status, branch_id, created_at
- **Status:** pending → completed | cancelled | refunded
- **Calculation:** total = subtotal + tax - discount

#### order_items
```sql
id | order_id | product_id | quantity | unit_price | line_total
```
- **Relationships:** BelongsTo order, BelongsTo product
- **Indexes:** order_id, product_id
- **Calculation:** line_total = quantity * unit_price

#### payments
```sql
id | order_id | method | amount | reference | status | notes
```
- **Relationships:** BelongsTo order
- **Method:** cash | card | check | mobile | other
- **Status:** pending | completed | failed | refunded
- **Indexes:** order_id, method, status

---

## 3. Role-Based Access Control

### Roles & Permissions

#### Cashier (8 permissions)
- View products
- View inventory
- Create orders
- Complete orders
- View orders
- Record payments
- View payments
- Create user (limited)

**Branch:** Restricted to assigned branch  
**Cannot:** Refund, Edit staff, Manage products

#### Manager (15+ permissions)
- All Cashier permissions
- User management (create, deactivate, edit)
- Branch management
- Product management (create, update, delete)
- Stock adjustment
- Refund payments
- View reports

**Branch:** Restricted to assigned branch  
**Cannot:** System settings, Role assignment

#### Admin (All permissions)
- Complete system access
- Multi-branch visibility
- User role assignment
- System configuration
- Full reporting

**Branch:** Unrestricted

---

## 4. API Endpoints

### Authentication (`/api/v1/auth`)
```
POST   /auth/login                 # Public - get token
POST   /auth/logout                # Protected - revoke token
GET    /auth/me                    # Protected - current user
```

### Users (`/api/v1/users`) - Manager+ only
```
POST   /users                      # Create cashier
GET    /users                      # List users (filtered by branch)
GET    /users/{id}                 # Get user details
POST   /users/{id}/activate        # Activate user
POST   /users/{id}/deactivate      # Deactivate user
DELETE /users/{id}                 # Delete user
```

### Branches (`/api/v1/branches`) - Manager+ only
```
POST   /branches                   # Create branch
GET    /branches                   # List branches
GET    /branches/{id}              # Get branch details
PUT    /branches/{id}              # Update branch
DELETE /branches/{id}              # Delete branch
```

### Products (`/api/v1/products`) - Cashier+ for view
```
GET    /products                   # List products (full)
GET    /products/{id}              # Get product details (full)

🚀 KEYBOARD-OPTIMIZED:
GET    /products/barcode/{barcode} # Fast barcode lookup
GET    /products/search/quick?q=   # Quick search (autocomplete)

POST   /products                   # Create (Manager+)
PUT    /products/{id}              # Update (Manager+)
DELETE /products/{id}              # Delete (Manager+)
POST   /products/{id}/adjust-stock # Adjust stock (Manager+)
```

### Orders (`/api/v1/orders`) - Cashier+
```
POST   /orders                     # Create new order
GET    /orders                     # List orders
GET    /orders/{id}                # Get order details (full)

🚀 KEYBOARD-OPTIMIZED:
GET    /orders/{id}/summary        # Minimal summary
POST   /orders/{id}/add-item       # Quick add item
DELETE /orders/{id}/items/{item}   # Remove item
POST   /orders/{id}/quick-pay      # Fast payment

POST   /orders/{id}/complete       # Complete order
POST   /orders/{id}/cancel         # Cancel order
```

### Payments (`/api/v1/orders/{id}/payments`)
```
POST   /payments                   # Record payment (standard)
GET    /payments                   # Get payment history
GET    /payments/summary           # Get payment summary
POST   /payments/refund            # Refund payment (Manager+)
```

---

## 5. Authentication Flow

### Login
```bash
POST /api/v1/auth/login
{
  "email": "cashier@example.com",
  "password": "password123"
}

Response 200:
{
  "success": true,
  "data": {
    "token": "1|abc123def456...",  # Sanctum token
    "user": {
      "id": 1,
      "name": "John Cashier",
      "email": "cashier@example.com",
      "branch_id": 1,
      "roles": ["cashier"],
      "permissions": [...]
    }
  }
}
```

### Authenticated Requests
```
All subsequent requests must include:
Authorization: Bearer 1|abc123def456...
Content-Type: application/json
Accept: application/json
```

### Logout
```bash
POST /api/v1/auth/logout
Authorization: Bearer {token}

Response 200:
{
  "success": true,
  "message": "Logged out successfully"
}
# Token is revoked server-side
```

---

## 6. Error Handling

### Standard Error Response
```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field": ["Specific validation error"]
  }
}
```

### HTTP Status Codes
| Code | Meaning | Retry? |
|------|---------|--------|
| 200 | OK | No |
| 201 | Created | No |
| 400 | Bad request | No |
| 401 | Unauthorized (token invalid/expired) | Yes |
| 403 | Forbidden (no permission) | No |
| 404 | Not found | No |
| 422 | Validation failed | No |
| 500 | Server error | Yes (backoff) |

### Common Errors
```
401 Unauthorized: Token expired → Refresh or re-login
403 Forbidden: User lacks permission → Check role
422 Validation: Invalid input → Fix and retry
404 Not Found: Resource deleted → Handle gracefully
500 Server Error: Internal issue → Retry with backoff
```

---

## 7. Transaction Processing

### Order Lifecycle
```
1. CREATE
   Status: pending
   Total: 0 (no items)
   
2. ADD ITEMS (repeat)
   Recalculate: subtotal, tax, total
   Update: remaining_balance = total - paid
   
3. COMPLETE
   Lock order
   Calculate final totals
   Status: pending → completed
   
4. PAYMENT (can be before/during/after completion)
   Create payment record
   Update: paid_amount, remaining_balance
   If remaining_balance ≤ 0:
     - Auto-complete if pending
     - Status: completed
   
5. RESULT
   Status: completed | cancelled | refunded
   Final: paid_amount, remaining_balance, total
```

### Transaction Safety
All mutations use database transactions:
```
- Add item: BEGIN → INSERT item → UPDATE order totals → COMMIT
- Record payment: BEGIN → INSERT payment → UPDATE order balance → COMMIT
- Cancel order: BEGIN → UPDATE status → DELETE items → COMMIT
```

---

## 8. Performance Optimizations

### Database Indexes
```
products:
  - idx_products_branch_id: (branch_id)
  - idx_products_sku: (sku) ← Fast barcode lookup
  - idx_products_is_active: (is_active)
  - UNIQUE (branch_id, sku): ← Composite unique per branch

orders:
  - idx_orders_order_number: (order_number)
  - idx_orders_branch_id: (branch_id)
  - idx_orders_cashier_id: (cashier_id)
  - idx_orders_status: (status)
  - idx_orders_created_at: (created_at)

order_items:
  - idx_order_items_order_id: (order_id)
  - idx_order_items_product_id: (product_id)
```

### Query Optimization
- **N+1 Prevention:** Use `.with()` to eager load relationships
- **Pagination:** Default 15 per page, max configurable
- **Filtering:** All filter operations use WHERE clauses before SELECT
- **Selective:** Keyboard endpoints return minimal fields

### Keyboard-Optimized Response Sizes
| Endpoint | Payload |
|----------|---------|
| Barcode search | ~300 bytes |
| Quick search | ~500 bytes |
| Add item | ~200 bytes |
| Summary | ~300 bytes |
| Quick pay | ~150 bytes |

---

## 9. Deployment & Configuration

### Environment Variables
```
.env file:
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_system
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SANCTUM_EXPIRATION=60  # days
```

### Running Backend
```bash
# Start Laravel development server
php artisan serve --port=8000

# API available at: http://localhost:8000/api/v1/

# Run migrations (first time)
php artisan migrate

# Seed test data
php artisan db:seed --class=DatabaseSeeder
```

---

## 10. Testing Checklist

### Authentication
- [ ] Login with valid credentials
- [ ] Login fails with invalid password
- [ ] Token stored and sent with requests
- [ ] Logout revokes token

### Cashier Workflow
- [ ] Create order
- [ ] Add item by barcode
- [ ] Add multiple items
- [ ] Remove item
- [ ] See updated totals
- [ ] Record cash payment
- [ ] Record card payment
- [ ] Partial payment (balance remains)
- [ ] Complete payment (auto-completes order)

### Manager Functions
- [ ] Create user (cashier)
- [ ] Deactivate user
- [ ] Create branch
- [ ] Adjust product stock
- [ ] Refund payment

### Branch Isolation
- [ ] Cashier in Branch A cannot see Branch B orders
- [ ] Products isolated by branch
- [ ] Users assigned to branches

---

## 11. Useful Debug Commands

```bash
# Check test users
php artisan tinker
>>> User::all()
>>> Order::count()

# Clear cache
php artisan cache:clear
php artisan config:clear

# Watch logs
tail -f storage/logs/laravel.log

# Database access (MySQL)
mysql -u root pos_system
SELECT COUNT(*) FROM orders;
```

---

## 12. Frontend Integration Points

### Keyboard-Driven Screen Expected Flow
```
1. Startup: POST /auth/login → get token
2. Create Order: POST /orders → get order_id
3. Main Loop:
   a. Barcode scan: GET /products/barcode/{barcode}
   b. Add item: POST /orders/{id}/add-item
   c. Display: GET /orders/{id}/summary
   d. Repeat 3a-c for each item
4. Payment:
   a. POST /orders/{id}/quick-pay
   b. Auto-complete if fully paid
5. Logout: POST /auth/logout
```

### Expected Latencies
- **Barcode scan → Product found:** 10-20ms
- **Add item → Totals updated:** 50-100ms
- **Payment recorded → Balance updated:** 50-100ms
- **Total latency perceived by user:** <200ms (instant feel)

---

## 13. Documentation Files

- `API_KEYBOARD_OPTIMIZATION.md` - Deep dive into keyboard endpoints
- `API_KEYBOARD_QUICK_REFERENCE.md` - Quick reference guide for frontend dev
- `KEYBOARD_DRIVEN_POS_FRONTEND.md` - Original frontend requirements
- `PAYMENT_HANDLING_SYSTEM.md` - Payment processing details
- `POS_DATABASE_SCHEMA.md` - Detailed schema documentation

---

*Generated: January 27, 2026*  
*For: Electron Desktop POS Frontend*  
*Backend: Laravel 12 REST API*
