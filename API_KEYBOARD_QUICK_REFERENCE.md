# Keyboard-Driven POS API Quick Reference

## Core Keyboard Flow

### 1. Create Order
```bash
POST /api/v1/orders
{
  "branch_id": 1,
  "discount": 0,
  "notes": null
}
# Returns: {order_id, order_number, status: "pending"}
```

### 2. Add Items (Fast Loop - Barcode Scan)
```bash
# Step 1: Lookup product by barcode
GET /api/v1/products/barcode/8001234567890
# Returns: ~300 bytes {id, sku, name, price, stock, available}

# Step 2: Add to order
POST /api/v1/orders/{order_id}/add-item
{
  "product_id": 5,
  "quantity": 2
}
# Returns: ~200 bytes {subtotal, tax, total, items}

# Step 3: Show updated totals (repeat every ~5 sec)
GET /api/v1/orders/{order_id}/summary
# Returns: ~300 bytes {number, items, subtotal, tax, discount, total, paid, balance}
```

### 3. Payment (Fast Path)
```bash
POST /api/v1/orders/{order_id}/quick-pay
{
  "method": "cash",  # "cash" | "card" | "check" | "mobile" | "other"
  "amount": 165.00
}
# Returns: ~150 bytes {paid, balance, status, complete}
```

---

## Typical Response Times
- Barcode lookup: **10-20ms**
- Add item: **50-100ms**
- Summary: **10-15ms**
- Quick pay: **50-100ms**

**Total per item:** ~150-200ms (user perceives as instant)

---

## Frontend Pseudo-Code (Keyboard-Driven)

```javascript
// Initialize
const orderId = await createOrder(branchId);
let orderSummary = { items: 0, total: 0, balance: 0 };

// Main loop: Wait for barcode scan or numeric input
while (orderIsOpen) {
  const input = await waitForKeyboardInput();
  
  if (isBarcodeFormat(input)) {
    // Barcode scanned
    const product = await getProductByBarcode(input);
    if (product.available) {
      showProduct(product);
      quantity = await getNumericInput("Qty: ");
      await addItem(orderId, product.id, quantity);
      orderSummary = await getOrderSummary(orderId);
      displayTotals(orderSummary);
    }
  } 
  else if (isPaymentKey(input)) {
    // Payment key pressed (F1, etc)
    displayPaymentScreen();
    method = await selectPaymentMethod(); // Cash, Card, etc
    amount = await getNumericInput("Amount: ");
    result = await quickPay(orderId, method, amount);
    
    if (result.complete) {
      showReceiptAndComplete(orderId);
      orderId = await createOrder(branchId); // New order
    }
  }
}
```

---

## HTTP Header Requirements

```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## Response Format (All Endpoints)

```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": { ... }  // Only if success: true
}
```

---

## Branch-Based Filtering

All endpoints filter by user's `branch_id` automatically:
- If user has `branch_id` → restricted to that branch
- If user is admin without branch → can see all branches

---

## Network Considerations

### Bandwidth Per Order
- 100 items/hour order entry
- Avg 250 bytes per API call
- ~122 calls/hour (adds, summaries, payments)
- = **~30 KB/hour** (essentially free on modern networks)

### Latency Sensitivity
- Keyboard cashiers expect <200ms response time
- All optimized endpoints designed for <100ms
- Use connection pooling in frontend
- Consider local caching of products

---

## Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | Success | Proceed |
| 201 | Created | Proceed |
| 400 | Bad request | Show error to user, retry |
| 403 | Forbidden | User lacks permission (branch mismatch) |
| 404 | Not found | Product/order doesn't exist |
| 422 | Unprocessable | Validation error (qty, amount, etc) |
| 500 | Server error | Retry, contact support |

---

## Authentication Flow

```bash
# 1. Login
POST /api/v1/auth/login
{
  "email": "cashier@example.com",
  "password": "password"
}
# Returns: {token, user: {id, name, branch_id, roles}}

# 2. Use token in subsequent requests
Authorization: Bearer {token}

# 3. Verify session
GET /api/v1/auth/me
# Returns: Current user info

# 4. Logout
POST /api/v1/auth/logout
```

---

## Product Search Endpoints

### Option 1: Barcode (Preferred)
```
GET /api/v1/products/barcode/8001234567890
Fast: 10-20ms, works with barcode scanners
```

### Option 2: Quick Search (Typing)
```
GET /api/v1/products/search/quick?q=cola
Returns: Top 10 matching products
```

### Option 3: Full Search (Slow)
```
GET /api/v1/products?search=cola&per_page=50
Returns: Detailed product info (use sparingly)
```

---

## Optimization Tips for Frontend

1. **Cache products after lookup**
   ```javascript
   const cache = {};
   const product = await getProductByBarcode(sku);
   cache[product.id] = product;
   ```

2. **Debounce summary refreshes**
   ```javascript
   clearTimeout(summaryTimer);
   summaryTimer = setTimeout(() => {
     fetchOrderSummary();
   }, 5000); // Refresh every 5sec max
   ```

3. **Local validation before API**
   ```javascript
   if (quantity <= 0) {
     showError("Invalid quantity");
     return; // Don't call API
   }
   ```

4. **Connection pooling**
   ```javascript
   const http = axios.create({
     timeout: 5000,
     keepAlive: true
   });
   ```

5. **Batch operations (future)**
   ```javascript
   // Post batch adds if network is slow
   POST /api/v1/orders/{id}/batch-add-items
   [{product_id, qty}, {product_id, qty}, ...]
   ```

---

## Testing Keyboard Flow

```bash
# 1. Get auth token
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"cashier@example.com","password":"password"}'

# 2. Create order
curl -X POST http://localhost/api/v1/orders \
  -H "Authorization: Bearer {token}" \
  -d '{"branch_id":1}'

# 3. Test barcode search
curl -X GET http://localhost/api/v1/products/barcode/8001234567890 \
  -H "Authorization: Bearer {token}"

# 4. Add item
curl -X POST http://localhost/api/v1/orders/1/add-item \
  -H "Authorization: Bearer {token}" \
  -d '{"product_id":5,"quantity":2}'

# 5. Get summary
curl -X GET http://localhost/api/v1/orders/1/summary \
  -H "Authorization: Bearer {token}"

# 6. Quick pay
curl -X POST http://localhost/api/v1/orders/1/quick-pay \
  -H "Authorization: Bearer {token}" \
  -d '{"method":"cash","amount":165.00}'
```

---

## Database Schema (Relevant)

```
products:
  - id (PK)
  - branch_id (FK) ← filtered by cashier's branch
  - sku (UNIQUE per branch) ← barcode lookup here
  - name, price, stock_qty
  - is_active

orders:
  - id (PK)
  - branch_id (FK)
  - cashier_id (FK)
  - order_number (unique)
  - subtotal, tax, discount, total
  - paid_amount, remaining_balance
  - status (pending → completed)

order_items:
  - id (PK)
  - order_id (FK)
  - product_id (FK)
  - quantity, unit_price, line_total

payments:
  - id (PK)
  - order_id (FK)
  - method, amount
  - status (completed)
```

---

## Common Workflows

### Cash Sale
```
1. GET /products/barcode/SKU
2. POST /orders
3. POST /orders/{id}/add-item (repeat for each item)
4. GET /orders/{id}/summary
5. POST /orders/{id}/quick-pay {cash, amount}
6. Status: complete → show receipt
```

### Card Sale
```
Same as cash, but use:
POST /orders/{id}/quick-pay {method: "card", amount}
```

### Partial Payment
```
1. POST /orders/{id}/quick-pay {method: "cash", amount: 50}
2. Get balance_remaining from response
3. User can pay more with another payment
4. Auto-completes when balance = 0
```

### Split Payment
```
1. POST /orders/{id}/quick-pay {method: "cash", amount: 100}
2. POST /orders/{id}/quick-pay {method: "card", amount: 65}
3. Auto-completes when fully paid
```

---

*Last Updated: January 27, 2026*
*For Electron Desktop POS Frontend - Keyboard-Driven Interface*
