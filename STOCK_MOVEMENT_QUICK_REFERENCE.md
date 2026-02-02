# Stock Movement Tracking - Quick Reference

---

## At a Glance

**What:** Automatic recording of every inventory change  
**Why:** Complete audit trail + data integrity verification  
**How:** Every stock change creates a `StockMovement` record with reason & balance  

---

## Movement Types

```
TYPE              WHEN                          QUANTITY    EXAMPLE
─────────────────────────────────────────────────────────────────────
sale              Order completed               negative    -2 units sold
adjustment        Manager adjusts stock         +/-         +50 from shipment
return            Completed order cancelled     positive    +2 returned
damage            Damage reported               negative    -3 damaged
inventory_count   Physical count variance       +/-         -1 (count mismatch)
```

---

## Key Data in Each Movement

```
Field             Example                     Purpose
─────────────────────────────────────────────────────────────────
product_id        5                           Which product
type              'sale'                      What kind of change
quantity          -2                          How much (pos/neg)
balance_qty       48                          Stock AFTER change
reference_id      42                          Order/adjustment ID
user_id           3                           Who made change
notes             "Order ORD-20260127-001"    Why (audit trail)
created_at        2026-01-27 14:30:45         When
```

---

## Code Examples

### Record a Movement (manual)
```php
use App\Models\StockMovement;

// Record damage
StockMovement::record(
    branchId: 1,
    productId: 5,
    type: 'damage',
    quantity: -3,
    meta: [
        'user_id' => auth()->user()->id,
        'notes' => 'Shelf collapse - 3 units destroyed',
    ]
);
```

### Get Product History
```php
$movements = StockMovement::forProduct(5)
    ->recent(30)  // Last 30 days
    ->with(['user', 'product'])
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($movements as $m) {
    echo "{$m->type}: {$m->quantity} ({$m->user->name}) - {$m->notes}\n";
}
```

### Generate Daily Sales Report
```php
$report = StockMovement::where('branch_id', 1)
    ->byType('sale')
    ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
    ->with('product')
    ->get()
    ->groupBy('product_id')
    ->map(function ($movements) {
        return [
            'product' => $movements->first()->product,
            'units_sold' => -$movements->sum('quantity'),
        ];
    });
```

### Verify Stock Integrity
```php
$movement = StockMovement::find(125);
$product = $movement->product;

// What the balance SHOULD be
$shouldBe = $movement->balance_qty;

// What it is now
$actualNow = $product->stock_qty;

// If they don't match, something went wrong
if ($shouldBe !== $actualNow) {
    Log::error("Stock discrepancy for {$product->sku}");
}
```

---

## API Endpoints

### List Movements
```bash
GET /api/v1/stock-movements?type=sale&days=7&limit=50
```
Returns recent movements filtered by type and date range.

### Product History
```bash
GET /api/v1/stock-movements/products/5/history?days=30
```
Full movement history for one product.

### Movement Summary
```bash
GET /api/v1/stock-movements/summary?days=30
```
Aggregated data: total by type, by product, totals.

### Movement Details
```bash
GET /api/v1/stock-movements/125
```
Full details of a specific movement.

---

## When Movements Happen (Automatic)

### Order Completed
```
Action: POST /api/v1/orders/42/complete
Result:
  - For each item in order:
    - Product stock reduced
    - Movement recorded (type='sale', qty=negative)
    - Balance stored
```

### Order Cancelled (if was completed)
```
Action: POST /api/v1/orders/42/cancel
Result:
  - For each item in order:
    - Product stock restored
    - Movement recorded (type='return', qty=positive)
    - Balance stored
```

### Stock Adjusted (manual)
```
Action: POST /api/v1/products/5/adjust-stock {"quantity": 50}
Result:
  - Product stock increased by 50
  - Movement recorded (type='adjustment', qty=+50)
  - Balance stored
```

---

## Transaction Safety

All stock operations are atomic:

```
BEGIN TRANSACTION
  ├─ Update product.stock_qty
  ├─ Create stock_movement record
  └─ COMMIT (or ROLLBACK on error)

Result: Both succeed together or both fail together
No partial updates possible
```

---

## Preventing Issues

✅ **Always recorded:** Every stock change creates a movement  
✅ **Can't go negative:** System prevents stock < 0 (validates on change)  
✅ **Permanent records:** Movements are immutable (never deleted/updated)  
✅ **Branch isolated:** Users only see their branch's movements  
✅ **Audit trail:** User ID, timestamp, reason all recorded  

---

## Common Queries

### Get all sales today
```php
StockMovement::byType('sale')
    ->whereDate('created_at', today())
    ->get();
```

### Find product with most movements
```php
StockMovement::with('product')
    ->recent(30)
    ->get()
    ->groupBy('product_id')
    ->map(fn($g) => count($g))
    ->sortDesc()
    ->first();  // Most movements
```

### Get movements by specific user
```php
StockMovement::where('user_id', 7)->recent(7)->get();
```

### Find adjustments (manual corrections)
```php
StockMovement::byType('adjustment')->get();
```

### Check for stock anomalies
```php
// Movements with balance < 0 (shouldn't happen)
StockMovement::whereRaw('balance_qty < 0')->get();

// Days with unusually high sales
StockMovement::byType('sale')
    ->recent(30)
    ->get()
    ->groupBy(fn($m) => $m->created_at->toDateString())
    ->map(fn($g) => abs($g->sum('quantity')))
    ->sortDesc();
```

---

## Troubleshooting

### Stock doesn't match movements
Check the movement chain:
```php
$product = Product::find(5);
$movements = StockMovement::forProduct(5)->orderBy('created_at')->get();

echo "Current: {$product->stock_qty}\n";
foreach ($movements as $m) {
    echo "{$m->created_at}: {$m->type} {$m->quantity} → {$m->balance_qty}\n";
}
```

### Missing movements for an order
Check:
1. Was order completed? (status = 'completed')
2. Did it have items? (items count > 0)
3. Check error logs for transaction rollback

### Movement recorded but stock not changed
This shouldn't happen (transaction ensures both), but check:
```php
$m = StockMovement::find(125);
$expectedStock = $m->balance_qty;
$actualStock = $m->product->stock_qty;

// If not equal, data integrity issue
```

---

## Database Queries

### View all movements (raw SQL)
```sql
SELECT 
    sm.id, 
    p.sku, 
    sm.type, 
    sm.quantity, 
    sm.balance_qty, 
    u.name, 
    sm.created_at
FROM stock_movements sm
JOIN products p ON sm.product_id = p.id
LEFT JOIN users u ON sm.user_id = u.id
WHERE sm.branch_id = 1
ORDER BY sm.created_at DESC
LIMIT 50;
```

### Find discrepancies
```sql
-- Check for impossible states
SELECT sm.* 
FROM stock_movements sm 
WHERE sm.balance_qty < 0 
  AND sm.type NOT IN ('damage', 'inventory_count');
```

---

## Performance Notes

- **Indexed on:** product_id, branch_id, type, created_at
- **Typical query times:** <100ms for most queries
- **Storage:** ~200 bytes per movement record
- **Daily volume (POS with 100 sales/day):** ~100 movements

---

## Testing

### Create test scenario
```bash
# 1. Create order
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"branch_id": 1, "discount": 0}'

# 2. Add item
curl -X POST http://localhost:8000/api/v1/orders/1/add-item \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 5, "quantity": 2}'

# 3. Complete order
curl -X POST http://localhost:8000/api/v1/orders/1/complete \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"tax_rate": 0.1}'

# 4. Check movements
curl http://localhost:8000/api/v1/stock-movements?type=sale \
  -H "Authorization: Bearer {token}"
```

---

*Last Updated: January 27, 2026*
