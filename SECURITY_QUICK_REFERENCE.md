# Security Implementation - Quick Reference

**Last Updated:** January 28, 2026

---

## Rate Limiting Overview

| Endpoint | Limit | Window |
|----------|-------|--------|
| `/auth/login` | 5/min | 1 min |
| `/auth/*` | 30/min | 1 min |
| `/reports/*` | 30/min | 1 min |
| `/stock-movements/*` | 30/min | 1 min |
| All others | 60/min | 1 min |

**Rate Limit Response (429):**
```json
{
  "success": false,
  "error": "Rate limit exceeded. Please try again later.",
  "status": 429
}
```

---

## Audit Logging

**Actions Logged:**
- ✅ login / logout
- ✅ order_created / order_completed
- ✅ payment_recorded / payment_refunded
- ✅ user_created / user_deactivated
- ✅ product_created / product_deleted
- ✅ All security denials

**Query Examples:**
```php
// Recent logs
AuditLog::recent()->get();

// User activity
AuditLog::forUser($userId)->recent()->get();

// Action type
AuditLog::forAction('login')->recent(50)->get();

// Date range
AuditLog::inDateRange($start, $end)->get();
```

---

## Sensitive Endpoints

### Refund Processing
```
POST /api/v1/orders/{order}/payments/refund
{
  "amount": 100.00,
  "confirm": true      // REQUIRED
}
```
- Requires: Manager or Admin
- Logs: payment_refunded

### User Deactivation
```
POST /api/v1/users/{user}/deactivate
```
- Requires: Manager or Admin
- Logs: user_deactivated

### Stock Adjustment
```
POST /api/v1/products/{product}/adjust-stock
{
  "quantity": 50,
  "reason": "Inventory count"
}
```
- Requires: Manager or Admin
- Logs: product_stock_adjusted

### User Creation
```
POST /api/v1/users
{
  "name": "John",
  "email": "john@example.com",
  "role": "cashier"     // or "manager", "admin"
}
```
- Requires: Manager or Admin
- Admin role: Admin only
- Logs: user_created

### Product Deletion
```
DELETE /api/v1/products/{product}
```
- Requires: Admin only
- Logs: product_deletion_attempt

### Branch Deletion
```
DELETE /api/v1/branches/{branch}
```
- Requires: Admin only
- Logs: branch_deletion_attempt

---

## Error Responses

### Standard Format
```json
{
  "success": false,
  "error": "Error message",
  "status": 400
}
```

### Validation Errors (422)
```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "email": ["Email is required"],
    "password": ["Password must be 8+ chars"]
  },
  "status": 422
}
```

### HTTP Status Codes
- 400: Bad request
- 401: Unauthorized
- 403: Forbidden
- 404: Not found
- 422: Validation error
- 429: Rate limit exceeded
- 500: Server error

---

## Middleware Stack

**Applied to all API routes:**
1. ForceJsonResponse - Ensures JSON output
2. RateLimitApi - Rate limiting
3. AuditLogging - Action logging
4. throttle:api - Laravel throttle
5. SubstituteBindings - Route model binding

**Applied selectively:**
- `protect_sensitive` - User, Product, Branch, Payment endpoints

---

## Audit Log Table

**Schema:**
```sql
audit_logs {
  id (PRIMARY KEY)
  user_id (nullable, indexed)
  action (indexed)
  entity_type (indexed, nullable)
  entity_id (indexed, nullable)
  method
  endpoint
  ip_address (indexed)
  user_agent
  response_code (indexed)
  changes (JSON)
  details (JSON)
  created_at (indexed)
}
```

---

## Security Features Checklist

- [x] Rate limiting (per IP/user)
- [x] Audit logging (all operations)
- [x] Sensitive endpoint protection
- [x] Error response handling
- [x] IP address tracking
- [x] User agent tracking
- [x] Role-based access control
- [x] Branch isolation
- [x] Confirmation requirements
- [x] Production error masking
- [x] Security event logging

---

## Common Security Tasks

### Find Failed Logins
```php
AuditLog::forAction('login')
    ->where('response_code', '!=', 200)
    ->recent(100)
    ->get();
```

### Find Suspicious IPs
```php
AuditLog::selectRaw('ip_address, count(*) as attempts')
    ->where('action', 'login')
    ->where('response_code', '!=', 200)
    ->groupBy('ip_address')
    ->having('attempts', '>', 5)
    ->get();
```

### Track User Activity
```php
AuditLog::where('user_id', $userId)->recent(50)->get();
```

### Monitor Refunds
```php
AuditLog::forAction('payment_refunded')
    ->with('user')
    ->recent(50)
    ->get();
```

### Detect Access Denials
```php
AuditLog::where('action', 'like', '%_denied')
    ->recent(100)
    ->get();
```

---

## Configuration

### Rate Limit Keys

```php
// Login
login:{ip}

// Auth operations
auth:{user_id} or auth:{ip}

// Heavy operations
heavy:{user_id} or heavy:{ip}

// General API
api:{user_id} or api:{ip}
```

### Customizing Limits

Edit: `app/Http/Middleware/RateLimitApi.php`

```php
if ($request->is('api/v1/auth/login')) {
    $limit = 5;  // Change this
    $decayMinutes = 1;
}
```

---

## Testing Security

### Test Rate Limiting
```bash
# Rapid requests (should get 429 after limit)
for i in {1..10}; do
  curl -X POST http://localhost/api/v1/auth/login \
    -d '{"email":"test@test.com","password":"test"}'
done
```

### Test Refund Confirmation
```bash
# Should fail (no confirm)
curl -X POST http://localhost/api/v1/orders/1/payments/refund \
  -H "Authorization: Bearer TOKEN" \
  -d '{"amount":100}'

# Should succeed (with confirm)
curl -X POST http://localhost/api/v1/orders/1/payments/refund \
  -H "Authorization: Bearer TOKEN" \
  -d '{"amount":100,"confirm":true}'
```

### Test Audit Logging
```php
// Check if action was logged
php artisan tinker
>>> AuditLog::where('action', 'order_created')->latest()->first();
```

---

## Maintenance

### Prune Old Audit Logs
```php
// Delete logs older than 6 months
AuditLog::where('created_at', '<', now()->subMonths(6))->delete();
```

### Check Database Growth
```sql
SELECT COUNT(*) as total_logs,
       DATE(created_at) as date
FROM audit_logs
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

### Monitor Rate Limit Hits
```php
AuditLog::where('action', 'like', 'rate_limit%')
    ->recent(100)
    ->count();
```

---

## Production Checklist

- [ ] Run migrations
- [ ] Set `APP_DEBUG=false`
- [ ] Use Redis for cache
- [ ] Configure log rotation
- [ ] Set up audit log pruning
- [ ] Test error responses
- [ ] Verify rate limiting
- [ ] Monitor suspicious activity
- [ ] Regular audit log review
- [ ] Backup audit logs

---

## Support

**Common Issues:**

Q: Rate limit too strict?
A: Adjust limits in RateLimitApi middleware

Q: Audit logs too large?
A: Implement pruning to remove old entries

Q: Refund not working?
A: Ensure "confirm": true in body + manager role

Q: Errors not being logged?
A: Check log file permissions and storage

---

**Version:** 1.0  
**Status:** Production Ready ✅
