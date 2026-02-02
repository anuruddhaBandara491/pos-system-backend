# Stock Movement Tracking System

**Created:** January 27, 2026  
**Purpose:** Comprehensive audit trail for all inventory changes with data integrity  

---

## Overview

The Stock Movement Tracking system provides complete visibility into all inventory changes across the POS system. Every change to product stock is recorded with:

- **Type of movement** (sale, adjustment, return, damage, inventory count)
- **Quantity changed** (positive or negative)
- **Reference information** (which order, user, date/time)
- **Stock balance** (inventory balance after movement)
- **Full audit trail** (user who made change, when, why)

---

## Key Features

✅ **Automatic Recording**
- Sales: Stock reduced when order is completed
- Manual adjustments: Recorded when manager adjusts stock
- Returns: Stock restored when order is cancelled
- Damage: Recorded for loss tracking

✅ **Data Integrity**
- All movements use database transactions
- Balance calculated and stored with each movement
- Prevents negative stock (except for damage tracking)
- Automatic validation on recording

✅ **Comprehensive Audit**
- Track every inventory change
- Know exactly who changed what and when
- Reference to original transaction (order, adjustment, etc.)
- Historical analysis and reporting

✅ **Performance Optimized**
- Indexed on product, branch, type, and date
- Fast queries for movement history
- Efficient reporting queries

---

## Database Schema

### stock_movements Table

```sql
CREATE TABLE stock_movements (
    id BIGINT PRIMARY KEY,
    branch_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    type ENUM('sale', 'adjustment', 'return', 'damage', 'inventory_count') NOT NULL,
    quantity INT NOT NULL,  -- positive or negative
    reference_type VARCHAR(255),  -- 'order', 'adjustment', 'return_authorization', etc
    reference_id BIGINT,  -- ID of related record (order_id, etc)
    user_id BIGINT,  -- who made the change
    notes TEXT,  -- reason or description
    balance_qty DECIMAL(8,2),  -- stock balance after this movement
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    -- Indexes for performance
    INDEX (product_id, created_at),
    INDEX (branch_id, created_at),
    INDEX (type, created_at),
    INDEX (reference_type)
);
```

### Relationships

- **Product**: Each movement belongs to one product
- **Branch**: Movements are branch-specific
- **User**: User who initiated the movement (cashier, manager, admin)

---

## StockMovement Model

```php
// Create/record a movement
StockMovement::record(
    branchId: 1,
    productId: 5,
    type: 'sale',  // or 'adjustment', 'return', 'damage', 'inventory_count'
    quantity: -3,  // negative for reductions
    meta: [
        'reference_type' => 'order',
        'reference_id' => 42,
        'user_id' => 7,
        'notes' => 'Order ORD-20260127-001'
    ]
);
```

### Model Methods

```php
// Scopes for filtering
StockMovement::forProduct($productId)->get();
StockMovement::forBranch($branchId)->get();
StockMovement::byType('sale')->get();
StockMovement::recent(30)->get();  // Last 30 days

// Get type label
$movement->getTypeLabel();  // 'Sale', 'Adjustment', etc

// Relationships
$movement->product;
$movement->branch;
$movement->user;

// Historical report
$report = StockMovement::getStockReport($branchId, '2026-01-01', '2026-01-31');
```

---

## Movement Types

### 1. **Sale** (Automatic on Order Completion)
- **Trigger:** When order status changes from pending → completed
- **Quantity:** Negative (reduces stock)
- **Reference:** order_id, order number
- **User:** Cashier who completed the order
- **Example:** Customer buys 2 beverages → stock reduced by 2

```
Type: sale
Quantity: -2
Reference Type: order
Reference ID: 42
Notes: "Order ORD-20260127-001: SKU-001 (Coca-Cola)"
```

### 2. **Adjustment** (Manual Stock Correction)
- **Trigger:** Manager uses POST /products/{id}/adjust-stock
- **Quantity:** Can be positive or negative
- **Reference:** adjustment, user_id
- **User:** Manager/Admin
- **Reason:** Examples: received shipment, inventory discrepancy, stock count

```
Type: adjustment
Quantity: +50
Reference Type: adjustment
User: Manager John
Notes: "Received shipment from supplier - 50 units"
```

### 3. **Return** (Order Cancellation)
- **Trigger:** When completed order is cancelled
- **Quantity:** Positive (restores stock)
- **Reference:** order_id, order number
- **User:** Manager who cancelled order
- **Reason:** Customer return, order error, etc.

```
Type: return
Quantity: +2
Reference Type: order_cancel
Reference ID: 42
Notes: "Order ORD-20260127-001 cancelled: returned 2 units"
```

### 4. **Damage** (Loss/Damage)
- **Trigger:** Manual recording by manager
- **Quantity:** Negative (removes stock)
- **Reference:** damage_report_id
- **User:** Manager reporting damage
- **Reason:** Product damaged, expired, lost, etc.

```
Type: damage
Quantity: -5
Reference Type: damage
User: Manager Jane
Notes: "Shelf collapse - 5 units damaged beyond recovery"
```

### 5. **Inventory Count** (Physical Count Adjustment)
- **Trigger:** Periodic inventory reconciliation
- **Quantity:** Can be positive or negative (difference from actual count)
- **Reference:** inventory_count_id
- **User:** Manager conducting count
- **Reason:** Variance from system vs physical count

```
Type: inventory_count
Quantity: -3
Reference Type: inventory_count
User: Manager Bob
Notes: "Monthly count - system showed 50, actual count 47"
```

---

## API Endpoints

### Get Stock Movements (with filters)

```
GET /api/v1/stock-movements
```

**Query Parameters:**
```
- type: 'sale' | 'adjustment' | 'return' | 'damage' | 'inventory_count'
- days: number of days back (1-365, default 30)
- limit: max results (1-500, default 50)
```

**Example Request:**
```bash
GET /api/v1/stock-movements?type=sale&days=7&limit=100
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 125,
      "product_id": 5,
      "product": {
        "id": 5,
        "sku": "SKU-001",
        "name": "Coca-Cola 1L"
      },
      "type": "sale",
      "type_label": "Sale",
      "quantity": -2,
      "balance_qty": 48,
      "reference_type": "order",
      "reference_id": 42,
      "user_id": 3,
      "user_name": "John Cashier",
      "notes": "Order ORD-20260127-001",
      "created_at": "2026-01-27T14:30:45.000Z"
    },
    ...
  ],
  "message": "Stock movements retrieved"
}
```

---

### Get Movement Summary (by branch and period)

```
GET /api/v1/stock-movements/summary
```

**Query Parameters:**
```
- days: number of days back (default 30)
```

**Example Request:**
```bash
GET /api/v1/stock-movements/summary?days=30
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "period_days": 30,
    "start_date": "2025-12-28",
    "end_date": "2026-01-27",
    "by_type": {
      "sale": {
        "count": 145,
        "quantity": -287,
        "movements": [...]
      },
      "adjustment": {
        "count": 8,
        "quantity": 250,
        "movements": [...]
      },
      "return": {
        "count": 3,
        "quantity": 12,
        "movements": [...]
      },
      "damage": {
        "count": 2,
        "quantity": -5,
        "movements": [...]
      }
    },
    "by_product": [
      {
        "product": {
          "id": 5,
          "sku": "SKU-001",
          "name": "Coca-Cola 1L"
        },
        "total_quantity": -25,
        "by_type": {
          "sale": -30,
          "adjustment": 5,
          "return": 0,
          "damage": 0
        }
      },
      ...
    ]
  }
}
```

---

### Get Product Movement History

```
GET /api/v1/stock-movements/products/{product}/history
```

**Query Parameters:**
```
- days: number of days back (default 30)
- limit: max results (default 50)
```

**Example Request:**
```bash
GET /api/v1/stock-movements/products/5/history?days=7&limit=100
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 5,
      "sku": "SKU-001",
      "name": "Coca-Cola 1L",
      "current_stock": 48
    },
    "movements": [
      {
        "id": 125,
        "type": "sale",
        "type_label": "Sale",
        "quantity": -2,
        "balance_qty": 50,
        "reference_type": "order",
        "reference_id": 42,
        "user_name": "John Cashier",
        "notes": "Order ORD-20260127-001",
        "created_at": "2026-01-27T14:30:45.000Z"
      },
      ...
    ]
  }
}
```

---

### Get Movement Details

```
GET /api/v1/stock-movements/{movement}
```

**Example Request:**
```bash
GET /api/v1/stock-movements/125
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 125,
    "product": {
      "id": 5,
      "sku": "SKU-001",
      "name": "Coca-Cola 1L"
    },
    "branch": {
      "id": 1,
      "name": "Main Branch"
    },
    "type": "sale",
    "type_label": "Sale",
    "quantity": -2,
    "balance_qty": 48,
    "reference_type": "order",
    "reference_id": 42,
    "user": {
      "id": 3,
      "name": "John Cashier",
      "email": "john@example.com"
    },
    "notes": "Order ORD-20260127-001",
    "created_at": "2026-01-27T14:30:45.000Z",
    "updated_at": "2026-01-27T14:30:45.000Z"
  }
}
```

---

## Automatic Recording on Order Operations

### When Order is Completed

```
BEFORE: Order status = pending
AFTER: Order status = completed

FOR EACH ITEM IN ORDER:
  - Record StockMovement with type='sale'
  - Quantity = -item.quantity
  - Reference: order_id, order_number
  - Product stock reduced by item quantity
  - Balance calculated and stored
```

**Example:**
```
Order ORD-20260127-001 completed:
  - Item 1: 2x Coca-Cola → Movement: type=sale, qty=-2
  - Item 2: 1x Orange Juice → Movement: type=sale, qty=-1
  
Product stocks after:
  - Coca-Cola: 50 → 48
  - Orange Juice: 20 → 19
```

### When Order is Cancelled

```
IF Order status = completed:
  FOR EACH ITEM IN ORDER:
    - Record StockMovement with type='return'
    - Quantity = +item.quantity
    - Product stock restored
    - Balance calculated

ELSE IF Order status = pending:
  - No stock change (items never reduced)
  - No movements recorded
```

---

## Data Integrity Guarantees

### Transaction Safety
All stock operations use database transactions:

```php
DB::beginTransaction();
try {
    // Update product stock
    $product->update(['stock_qty' => $newStock]);
    
    // Record movement
    StockMovement::record(...);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Both changes rolled back
}
```

### Stock Balance Verification
Each movement stores the balance AFTER the change:

```
Before: 50 units
Movement: -2
After: 48 units ✓ Stored in movement.balance_qty
```

You can verify data integrity by checking:
```php
$movement = StockMovement::find(125);
$product = $movement->product;

$shouldBe = $previousBalance + $movement->quantity;
$actualBalance = $movement->balance_qty;

if ($shouldBe !== $actualBalance) {
    // Data integrity issue!
}
```

### Preventing Negative Stock

The system prevents reducing stock below 0:

```php
$newStock = $product->stock_qty + $quantity;

if ($newStock < 0) {
    throw new \Exception("Insufficient stock");
}
```

**Exception:** Damage movements can go negative (for tracking purposes).

### Branch Isolation
All movements are branch-specific:

```php
StockMovement::where('branch_id', $userBranchId)->get();
```

Cashiers can only see/query their branch's movements.

---

## Reporting & Analytics

### Daily Sales Report
```php
$movements = StockMovement::where('branch_id', $branchId)
    ->byType('sale')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->with('product')
    ->get()
    ->groupBy('product_id');

foreach ($movements as $productId => $productMovements) {
    $totalSold = $productMovements->sum('quantity');  // negative, so sum is total reduction
}
```

### Low Stock Identification
```php
$movements = StockMovement::where('branch_id', $branchId)
    ->recent(7)
    ->byType('sale')
    ->get();

$salesByProduct = $movements->groupBy('product_id')
    ->map(fn($group) => -$group->sum('quantity'));

foreach ($salesByProduct as $productId => $unitsSold) {
    if ($unitsSold > $product->reorder_level) {
        // Low on stock - reorder needed
    }
}
```

### Stock Reconciliation
```php
$movements = StockMovement::where('branch_id', $branchId)
    ->where('type', 'inventory_count')
    ->recent(1)  // Last inventory count
    ->get();

// Find discrepancies
foreach ($movements as $movement) {
    if ($movement->quantity !== 0) {
        // Variance found - investigate
    }
}
```

### Audit Trail for Specific Order
```php
$movements = StockMovement::where('reference_type', 'order')
    ->where('reference_id', $orderId)
    ->with(['product', 'user'])
    ->orderBy('created_at')
    ->get();

foreach ($movements as $movement) {
    echo "User: {$movement->user->name}\n";
    echo "Product: {$movement->product->sku}\n";
    echo "Change: {$movement->quantity}\n";
    echo "Time: {$movement->created_at}\n";
}
```

---

## Testing the System

### Test Data - Sales Scenario

```bash
# 1. Create order
POST /api/v1/orders
{
  "branch_id": 1,
  "discount": 0
}
# Response: order_id = 42

# 2. Add items
POST /api/v1/orders/42/add-item
{
  "product_id": 5,
  "quantity": 2
}

# 3. Complete order (triggers stock reduction + movements)
POST /api/v1/orders/42/complete
{
  "tax_rate": 0.1
}

# 4. Verify stock was reduced
GET /api/v1/products/5
# stock_qty should be reduced by 2

# 5. Check movement was recorded
GET /api/v1/stock-movements?type=sale
# Should see new movement with quantity=-2
```

### Test Data - Adjustment Scenario

```bash
# 1. Adjust stock (manager only)
POST /api/v1/products/5/adjust-stock
{
  "quantity": 50,
  "reason": "Received shipment from supplier"
}
# Product stock increases by 50

# 2. Verify movement
GET /api/v1/stock-movements/products/5/history
# Should see movement: type=adjustment, qty=+50

# 3. Check summary
GET /api/v1/stock-movements/summary?days=1
# by_type.adjustment.count = 1
# by_type.adjustment.quantity = 50
```

### Test Data - Order Cancellation

```bash
# 1. Create and complete order (see above)

# 2. Cancel order
POST /api/v1/orders/42/cancel
# If order was completed, stock is restored

# 3. Verify stock was restored
GET /api/v1/products/5
# stock_qty should be increased

# 4. Check return movement
GET /api/v1/stock-movements?type=return
# Should see movement with positive quantity
```

---

## Troubleshooting

### Stock Discrepancy Between System and Physical Count

```php
// Get all movements in past 30 days
$movements = StockMovement::forProduct($productId)
    ->recent(30)
    ->orderBy('created_at')
    ->get();

// Calculate expected stock from movements
$openingStock = 100;  // from last physical count
$expectedStock = $openingStock;

foreach ($movements as $movement) {
    $expectedStock += $movement->quantity;
    echo "{$movement->type}: {$movement->quantity} → {$movement->balance_qty}\n";
}

// Compare with current system stock
$currentSystemStock = $product->stock_qty;

if ($expectedStock !== $currentSystemStock) {
    // Discrepancy found - investigate
}
```

### Missing Movement Records

Check if all operations wrapped in transactions and error handling:

```bash
# Check movement count
GET /api/v1/stock-movements/summary

# If lower than expected:
# 1. Check error logs
# 2. Verify all order completions are recorded
# 3. Run integrity check
```

---

## Future Enhancements

- [ ] Batch import movements from external systems
- [ ] Real-time movement WebSocket updates
- [ ] Movement approval workflow for large adjustments
- [ ] Mobile app for stock counts
- [ ] Barcode-based inventory reconciliation
- [ ] Movement reason/category requirements
- [ ] Stock variance alerts
- [ ] Historical stock analysis (trends, forecasting)

---

## Quick Reference: When Movements Are Created

| Operation | Type | Qty | Trigger |
|-----------|------|-----|---------|
| Customer buys items | sale | negative | Order completed |
| Manager adjusts stock | adjustment | +/- | adjust-stock endpoint |
| Order cancelled | return | positive | cancel order (if completed) |
| Damage/loss reported | damage | negative | manual (manager+) |
| Inventory count | inventory_count | +/- | manual (manager+) |

---

*Last Updated: January 27, 2026*  
*Database Migration: 2026_01_27_400000_create_stock_movements_table*
