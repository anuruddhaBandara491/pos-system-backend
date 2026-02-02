# Payment API Documentation

This document describes the payment endpoints for the POS System. All payment endpoints require authentication and use token-based authorization (Bearer token in Authorization header).

## Base URL
```
/api/v1/payments
```

## Authentication
All endpoints require:
- `Authorization: Bearer {token}` header
- User must have `record_payment` or higher permission
- Only `cashier`, `manager`, and `admin` roles can access

---

## 1. Submit Payment (Idempotent)

**Endpoint:** `POST /api/v1/payments/submit`

**Purpose:** Submit a payment for an order with idempotency protection to prevent duplicate charges.

### Request

**Required Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
Idempotency-Key: {UUID or hash}
```

**Body Parameters:**
```json
{
  "orderId": "string (required) - Order ID to pay for",
  "amount": "numeric (required) - Payment amount (> 0)",
  "method": "enum (required) - Payment method: cash|card|check",
  "reference": "string (optional) - Transaction ID or receipt number (max 100 chars)",
  "metadata": "object (optional) - Additional payment data (max 1024 bytes)"
}
```

**Example Request:**
```bash
curl -X POST http://localhost/api/v1/payments/submit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer token123" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{
    "orderId": "order_123",
    "amount": 50.00,
    "method": "cash",
    "reference": "CASH001",
    "metadata": {"cashier": "john_doe"}
  }'
```

### Response - Success (201 Created)

```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "paymentId": "pay_abc123def456",
    "orderId": "order_123",
    "amount": 50.00,
    "method": "cash",
    "status": "completed",
    "reference": "CASH001",
    "timestamp": "2026-01-29T10:30:45Z",
    "balanceRemaining": 50.00,
    "totalPaid": 50.00,
    "orderStatus": "partial"
  }
}
```

### Response - Errors

**400 Bad Request** - Invalid input or amount exceeds balance:
```json
{
  "success": false,
  "message": "Payment amount exceeds remaining balance",
  "errors": {
    "code": "AMOUNT_EXCEEDS_BALANCE",
    "requestedAmount": 150.00,
    "balanceRemaining": 100.00
  }
}
```

**409 Conflict** - Payment processing in progress or order already paid:
```json
{
  "success": false,
  "message": "Payment is currently being processed",
  "errors": {
    "code": "PAYMENT_PROCESSING",
    "idempotencyKey": "550e8400-e29b-41d4-a716-446655440000",
    "existingPaymentId": "pay_abc123def456"
  }
}
```

**409 Conflict** - Order already fully paid (duplicate Idempotency-Key):
```json
{
  "success": false,
  "message": "Order is already fully paid",
  "errors": {
    "code": "ORDER_ALREADY_PAID",
    "orderId": "order_123",
    "balanceRemaining": 0
  }
}
```

**404 Not Found** - Order doesn't exist:
```json
{
  "success": false,
  "message": "Order not found",
  "errors": {
    "code": "ORDER_NOT_FOUND"
  }
}
```

**500 Internal Server Error**:
```json
{
  "success": false,
  "message": "Internal server error",
  "errors": {
    "message": "Payment processing failed"
  }
}
```

### Idempotency Handling

- **Idempotency-Key Header** (Required): A unique identifier for this payment request
  - Can be a UUID or any unique string
  - Must be unique across all payment submissions
  - Prevents duplicate charges if request is retried

- **Processing Window**: 24 hours
  - Idempotency keys expire after 24 hours
  - After expiration, same key can be reused

- **Cached Response**: If idempotency key exists with status `completed`, the cached response is returned immediately
  - No new payment is created
  - Database changes are NOT replayed

---

## 2. Get Payment Balance

**Endpoint:** `GET /api/v1/payments/balance/{orderId}`

**Purpose:** Get the current payment balance for an order including payment history.

### Request

**URL Parameters:**
```
orderId (required) - The order ID to get balance for
```

**Example Request:**
```bash
curl -X GET http://localhost/api/v1/payments/balance/order_123 \
  -H "Authorization: Bearer token123"
```

### Response - Success (200 OK)

```json
{
  "success": true,
  "message": "Balance retrieved successfully",
  "data": {
    "orderId": "order_123",
    "totalAmount": 100.00,
    "totalPaid": 50.00,
    "balanceRemaining": 50.00,
    "status": "partial",
    "payments": [
      {
        "paymentId": "pay_abc123def456",
        "method": "cash",
        "amount": 50.00,
        "timestamp": "2026-01-29T10:30:45Z",
        "status": "completed"
      }
    ]
  }
}
```

### Response - Errors

**404 Not Found**:
```json
{
  "success": false,
  "message": "Order not found",
  "errors": {
    "code": "ORDER_NOT_FOUND",
    "orderId": "order_123"
  }
}
```

**500 Internal Server Error**:
```json
{
  "success": false,
  "message": "Failed to calculate balance",
  "errors": {
    "code": "BALANCE_CALCULATION_ERROR"
  }
}
```

### Status Values

- **unpaid**: Balance remaining, no payments made
- **partial**: Some amount paid, balance still remaining
- **paid**: Fully paid (balanceRemaining ≤ 0)

---

## 3. Get Payment Status

**Endpoint:** `GET /api/v1/payments/{paymentId}/status`

**Purpose:** Confirm the status of a specific payment with server-verified timestamp.

### Request

**URL Parameters:**
```
paymentId (required) - The payment ID to check status for
```

**Example Request:**
```bash
curl -X GET http://localhost/api/v1/payments/pay_abc123def456/status \
  -H "Authorization: Bearer token123"
```

### Response - Success (200 OK)

```json
{
  "success": true,
  "message": "Payment status confirmed",
  "data": {
    "paymentId": "pay_abc123def456",
    "orderId": "order_123",
    "amount": 50.00,
    "method": "cash",
    "paymentStatus": "completed",
    "timestamp": "2026-01-29T10:30:45Z",
    "confirmedAt": "2026-01-29T10:30:50Z",
    "balanceRemaining": 50.00,
    "totalPaid": 50.00,
    "orderStatus": "partial"
  }
}
```

### Response - Errors

**404 Not Found**:
```json
{
  "success": false,
  "message": "Payment not found",
  "errors": {
    "code": "PAYMENT_NOT_FOUND",
    "paymentId": "pay_invalid"
  }
}
```

**500 Internal Server Error**:
```json
{
  "success": false,
  "message": "Failed to confirm payment status",
  "errors": {
    "code": "STATUS_CONFIRMATION_ERROR"
  }
}
```

---

## 4. Get Payment History

**Endpoint:** `GET /api/v1/payments/history/{orderId}`

**Purpose:** Get complete payment history for an order with pagination support.

### Request

**URL Parameters:**
```
orderId (required) - The order ID to get history for
```

**Query Parameters:**
```
limit (optional, default: 100, max: 500) - Number of payments to return
offset (optional, default: 0) - Number of payments to skip
```

**Example Request:**
```bash
curl -X GET "http://localhost/api/v1/payments/history/order_123?limit=50&offset=0" \
  -H "Authorization: Bearer token123"
```

### Response - Success (200 OK)

```json
{
  "success": true,
  "message": "Payment history retrieved successfully",
  "data": {
    "orderId": "order_123",
    "totalPayments": 2,
    "totalAmount": 100.00,
    "totalPaid": 100.00,
    "payments": [
      {
        "paymentId": "pay_abc123def456",
        "method": "cash",
        "amount": 50.00,
        "timestamp": "2026-01-29T10:30:45Z",
        "status": "completed",
        "reference": "CASH001"
      },
      {
        "paymentId": "pay_xyz789uvw012",
        "method": "card",
        "amount": 50.00,
        "timestamp": "2026-01-29T10:35:20Z",
        "status": "completed",
        "reference": "CC-12345"
      }
    ]
  }
}
```

### Response - Errors

**404 Not Found**:
```json
{
  "success": false,
  "message": "Order not found",
  "errors": {
    "code": "ORDER_NOT_FOUND",
    "orderId": "order_123"
  }
}
```

**500 Internal Server Error**:
```json
{
  "success": false,
  "message": "Failed to retrieve payment history",
  "errors": {
    "code": "HISTORY_RETRIEVAL_ERROR"
  }
}
```

---

## Database Schema

### payments Table
```sql
CREATE TABLE payments (
  id VARCHAR(255) PRIMARY KEY COMMENT 'pay_*',
  order_id VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','card','check'),
  status ENUM('completed','pending','failed') DEFAULT 'completed',
  reference VARCHAR(255) NULLABLE,
  idempotency_key VARCHAR(255) UNIQUE NULLABLE,
  timestamp DATETIME,
  metadata JSON NULLABLE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_order_id (order_id),
  INDEX idx_idempotency_key (idempotency_key),
  INDEX idx_timestamp (timestamp),
  INDEX idx_status (status),
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

### payment_idempotency_keys Table
```sql
CREATE TABLE payment_idempotency_keys (
  id VARCHAR(255) PRIMARY KEY COMMENT 'UUID',
  idempotency_key VARCHAR(255) UNIQUE NOT NULL,
  payment_id VARCHAR(255) NULLABLE,
  status ENUM('processing','completed','failed') DEFAULT 'processing',
  response JSON NULLABLE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_idempotency_key (idempotency_key),
  INDEX idx_expires_at (expires_at),
  
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
```

---

## Error Codes Summary

| Code | Status | Description |
|------|--------|-------------|
| ORDER_NOT_FOUND | 404 | Order doesn't exist |
| PAYMENT_NOT_FOUND | 404 | Payment doesn't exist |
| AMOUNT_EXCEEDS_BALANCE | 400 | Payment amount > remaining balance |
| VALIDATION_FAILED | 400 | Input validation failed |
| PAYMENT_PROCESSING | 409 | Duplicate payment in processing |
| ORDER_ALREADY_PAID | 409 | Order is fully paid |
| BALANCE_CALCULATION_ERROR | 500 | Database calculation failed |
| STATUS_CONFIRMATION_ERROR | 500 | Cannot confirm payment status |
| HISTORY_RETRIEVAL_ERROR | 500 | Cannot retrieve payment history |

---

## Role-Based Access Control

**Cashier** (`record_payment` permission):
- Can submit payments: ✅
- Can check balance: ✅
- Can check payment status: ✅
- Can view payment history: ✅
- Can refund payments: ❌

**Manager** (`refund_payment` permission):
- All cashier permissions +
- Can refund payments: ✅

**Admin**:
- All permissions

---

## Implementation Notes

### Idempotency Protection
- Every payment submission must include unique `Idempotency-Key` header
- Same key within 24 hours returns cached response
- Prevents duplicate charges on network retry or user double-click

### Balance Calculation
- Real-time calculation from database
- Formula: `balanceRemaining = order.total - SUM(completed payments)`
- Handles decimal rounding (amounts stored as `DECIMAL(10,2)`)

### Concurrency Control
- Uses database row-level locking (`SELECT FOR UPDATE`)
- Prevents race conditions during concurrent payments
- Full ACID transaction compliance

### Status Transitions
- Payment created with status `completed` (synchronous processing)
- Status can be `pending` or `failed` for async/failed payments
- Order status updates based on payment completion

### Timestamps
- `timestamp` field: When payment was actually processed (UTC)
- `confirmedAt` field (response only): When client status was confirmed (UTC)
- Both in ISO 8601 format

---

## Integration with Electron Frontend

The payment system is accessible via IPC handlers in the Electron app:

```javascript
// IPC Channel Names
window.api.payments.submit({
  orderId,
  amount,
  method,
  reference,
  idempotencyKey  // UUID generated by frontend
})

window.api.payments.getBalance(orderId)
window.api.payments.getStatus(paymentId)
window.api.payments.getHistory(orderId, { limit, offset })
```

See [ELECTRON_IPC.md](./ELECTRON_IPC.md) for complete IPC documentation.

---

## Testing

### Manual Testing with cURL

**1. Submit Payment:**
```bash
curl -X POST http://localhost/api/v1/payments/submit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{
    "orderId": "order_123",
    "amount": 50.00,
    "method": "cash"
  }'
```

**2. Check Balance:**
```bash
curl -X GET http://localhost/api/v1/payments/balance/order_123 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**3. Get Payment Status:**
```bash
curl -X GET http://localhost/api/v1/payments/pay_abc123/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**4. Get Payment History:**
```bash
curl -X GET "http://localhost/api/v1/payments/history/order_123?limit=50" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Related Documentation

- [BACKEND_SETUP.md](./BACKEND_SETUP.md) - Backend architecture
- [ELECTRON_IPC.md](./ELECTRON_IPC.md) - Frontend communication
- [ROLES_PERMISSIONS.md](./ROLES_PERMISSIONS.md) - RBAC system
