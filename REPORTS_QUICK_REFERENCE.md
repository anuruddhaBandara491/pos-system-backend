# POS Reports - Quick Reference Guide

**Last Updated:** January 27, 2026

---

## Quick Endpoint Reference

| Endpoint | Method | Access | Purpose | Latency |
|----------|--------|--------|---------|---------|
| `/reports/daily-sales` | GET | Cashier+ | Daily sales by date | ~50ms |
| `/reports/cashier-sales` | GET | Cashier+ | Sales by cashier | ~75ms |
| `/reports/product-sales` | GET | Cashier+ | Sales by product | ~100ms |
| `/reports/profit` | GET | Manager+ | Profit analysis | ~150ms |

---

## Query Parameters

### All Reports
```
start_date=YYYY-MM-DD    (default: today)
end_date=YYYY-MM-DD      (default: today)
branch_id=1              (optional, manager/admin only)
```

### Product Sales Only
```
category=Beverages       (optional, filter by category)
```

### Profit Report Only
```
group_by=product|date    (default: product)
```

---

## Quick Examples

### Get Today's Sales
```bash
GET /api/v1/reports/daily-sales
```

### Get Sales for January
```bash
GET /api/v1/reports/daily-sales?start_date=2026-01-01&end_date=2026-01-31
```

### Get Cashier Performance Today
```bash
GET /api/v1/reports/cashier-sales
```

### Get Top Selling Products This Month
```bash
GET /api/v1/reports/product-sales?start_date=2026-01-01&end_date=2026-01-31
```

### Get Profit by Product This Month
```bash
GET /api/v1/reports/profit?group_by=product&start_date=2026-01-01&end_date=2026-01-31
```

### Get Daily Profit This Month (Admin)
```bash
GET /api/v1/reports/profit?group_by=date&start_date=2026-01-01&end_date=2026-01-31&branch_id=2
```

### Get Beverage Sales
```bash
GET /api/v1/reports/product-sales?category=Beverages
```

---

## Response Summary Format

### Daily Sales (Abbreviated)
```json
{
  "data": {
    "summary": {
      "total_sales": 15250.00,
      "total_transactions": 127,
      "avg_daily_sales": 492.26,
      "days_in_range": 31
    },
    "data": [
      {
        "date": "2026-01-31",
        "transaction_count": 5,
        "total_sales": 550.00,
        "avg_transaction": 110.00
      }
    ]
  }
}
```

### Cashier Sales (Abbreviated)
```json
{
  "data": {
    "summary": {
      "total_cashiers": 3,
      "total_sales": 15250.00,
      "total_transactions": 127
    },
    "data": [
      {
        "cashier_id": 2,
        "cashier_name": "John Smith",
        "transaction_count": 50,
        "total_sales": 6500.00,
        "avg_transaction": 130.00
      }
    ]
  }
}
```

### Product Sales (Abbreviated)
```json
{
  "data": {
    "summary": {
      "total_products": 12,
      "total_quantity_sold": 584,
      "total_sales": 15250.00
    },
    "data": [
      {
        "product_id": 1,
        "product_name": "Coca-Cola 1L",
        "quantity_sold": 156,
        "total_sales": 3900.00,
        "avg_price": 25.00
      }
    ]
  }
}
```

### Profit Report (Abbreviated)
```json
{
  "data": {
    "summary": {
      "total_revenue": 15250.00,
      "total_cost": 7625.00,
      "total_profit": 7625.00,
      "profit_margin_percent": 50.00
    },
    "data": [
      {
        "product_name": "Coca-Cola 1L",
        "quantity_sold": 156,
        "total_revenue": 3900.00,
        "total_cost": 1560.00,
        "total_profit": 2340.00,
        "profit_margin_percent": 60.00
      }
    ]
  }
}
```

---

## Key Metrics Explained

### Daily Sales
- **transaction_count:** Number of completed orders
- **total_sales:** Revenue (including tax)
- **subtotal:** Before tax and discounts
- **tax_collected:** Total tax
- **total_discount:** Total discounts given
- **avg_transaction:** Average order value

### Cashier Sales
- **transaction_count:** Orders completed by cashier
- **total_sales:** Total revenue generated
- **avg_transaction:** Average order value
- **min_transaction:** Smallest order
- **max_transaction:** Largest order
- **tax_collected:** Tax from this cashier

### Product Sales
- **quantity_sold:** Units sold
- **total_sales:** Revenue from product
- **avg_price:** Average selling price
- **min_price:** Lowest price ever charged
- **max_price:** Highest price ever charged
- **orders_count:** Number of orders containing product

### Profit Metrics
- **total_revenue:** Sales revenue
- **total_cost:** Cost of goods sold
- **total_profit:** Revenue - Cost
- **profit_margin_percent:** (Profit / Revenue) × 100

---

## Authorization Matrix

| Report | Cashier | Manager | Admin | Notes |
|--------|---------|---------|-------|-------|
| Daily Sales | ✓ | ✓ | ✓ | Branch restricted |
| Cashier Sales | ✓ | ✓ | ✓ | Branch restricted |
| Product Sales | ✓ | ✓ | ✓ | Branch restricted |
| Profit | ✗ | ✓ | ✓ | Manager+ only |

**Branch Filtering:**
- Cashier: Cannot filter (shows own branch only)
- Manager: Can specify own branch only
- Admin: Can specify any branch

---

## Common Use Cases

### Daily End-of-Day Close
```bash
GET /api/v1/reports/daily-sales?start_date=TODAY&end_date=TODAY
```
Returns today's total sales, transactions, and average order value.

### Weekly Performance Review
```bash
GET /api/v1/reports/cashier-sales?start_date=WEEK_START&end_date=WEEK_END
```
Compare cashier performance for the week.

### Inventory Replenishment
```bash
GET /api/v1/reports/product-sales?start_date=LAST_30_DAYS
```
Identify top sellers and slow movers.

### Monthly Profit Analysis
```bash
GET /api/v1/reports/profit?group_by=product&start_date=MONTH_START&end_date=MONTH_END
```
Analyze profitability by product.

### Branch Comparison (Admin)
```bash
GET /api/v1/reports/daily-sales?branch_id=1&start_date=MONTH_START&end_date=MONTH_END
GET /api/v1/reports/daily-sales?branch_id=2&start_date=MONTH_START&end_date=MONTH_END
```
Compare performance across branches.

---

## Date Formats

**Valid Format:** `YYYY-MM-DD`
```
✓ 2026-01-31
✓ 2026-12-25
✗ 01/31/2026
✗ 31-01-2026
✗ Jan 31, 2026
```

**Date Range Rules:**
- Start date must be ≤ End date
- Both dates required (or both omitted for today)
- Timezone: Server timezone

---

## Error Codes

| Status | Error | Solution |
|--------|-------|----------|
| 400 | Invalid date format | Use YYYY-MM-DD |
| 400 | Start date after end date | Swap dates |
| 403 | Permission denied | Check user role |
| 403 | Unauthorized branch access | Use own branch_id |
| 500 | Server error | Check logs |

---

## Performance Tips

1. **Limit Date Range:** Shorter ranges = faster queries
2. **Use Branch Filter:** Reduces data volume
3. **Cache Results:** Reuse within same request
4. **Batch Requests:** Combine related reports
5. **Off-peak Times:** Run heavy reports at night

---

## Integration Quick Start

### JavaScript/Fetch
```javascript
async function getReport(endpoint, filters = {}) {
  const params = new URLSearchParams(filters);
  const response = await fetch(
    `/api/v1/reports/${endpoint}?${params}`,
    { headers: { 'Authorization': `Bearer ${token}` } }
  );
  return response.json();
}

// Usage
const sales = await getReport('daily-sales', {
  start_date: '2026-01-01',
  end_date: '2026-01-31'
});
```

### React Component
```jsx
function SalesReport() {
  const [data, setData] = useState(null);
  
  useEffect(() => {
    fetch('/api/v1/reports/daily-sales')
      .then(r => r.json())
      .then(d => setData(d.data));
  }, []);
  
  return <div>{data?.summary?.total_sales}</div>;
}
```

### Vue Component
```vue
<script setup>
import { ref, onMounted } from 'vue';

const report = ref(null);

onMounted(async () => {
  const res = await fetch('/api/v1/reports/daily-sales');
  const json = await res.json();
  report.value = json.data;
});
</script>

<template>
  <div>{{ report?.summary?.total_sales }}</div>
</template>
```

---

## Formatted vs Raw Values

All monetary values provided in both formats:

**Raw (for calculations):**
```
"total_sales": 15250.00
```

**Formatted (for display):**
```
"total_sales_formatted": "15,250.00"
```

Use formatted values for UI display, raw for calculations.

---

## Sorting

All reports return results pre-sorted by most relevant metric:
- **Daily Sales:** By date (descending)
- **Cashier Sales:** By total_sales (descending)
- **Product Sales:** By total_sales (descending)
- **Profit Report:** By total_revenue or date (descending)

---

## Filtering Precedence

1. Authentication (role-based)
2. Branch access control (apply automatically)
3. Date range (BETWEEN clause)
4. Category (product_sales only)
5. Group_by (profit report only)

---

## Real-Time vs Batch

- **Real-Time:** Use with default dates (today) for live dashboards
- **Batch:** Use with date ranges for reports and exports
- **Caching:** Results valid for ~60 seconds

---

## Support & Troubleshooting

**Issue:** Getting empty results
- Check date range (no data for that period?)
- Verify branch filter matches user's branch
- Confirm orders have status = 'completed'

**Issue:** Slow queries
- Reduce date range
- Use specific branch_id
- Avoid excessive category filtering

**Issue:** Authorization errors
- Verify user role (Manager+ for profit)
- Check branch_id matches user's branch
- Confirm token is valid

---

**Version:** 1.0  
**Status:** Production Ready ✅
