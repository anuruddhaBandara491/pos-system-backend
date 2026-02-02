# Receipt & Invoice APIs - Quick Reference

---

## Endpoints at a Glance

### Receipts (Cashier+)

| Method | Endpoint | Returns | Purpose |
|--------|----------|---------|---------|
| GET | `/orders/{id}/receipt` | JSON | Structured receipt data |
| GET | `/orders/{id}/receipt/text` | Plain text | Thermal printer (58mm) |
| GET | `/orders/{id}/receipt/html` | HTML | Browser preview/PDF |
| POST | `/orders/{id}/receipt/reprint` | JSON | Reprint with flag |

### Invoices (Manager/Admin)

| Method | Endpoint | Returns | Purpose |
|--------|----------|---------|---------|
| GET | `/orders/{id}/invoice` | JSON | Full invoice data |
| GET | `/orders/{id}/invoice/csv` | CSV | Excel import |
| GET | `/orders/{id}/invoice/json` | JSON | Integration/export |

---

## Receipt JSON Structure

```json
{
  "header": {
    "store_name": "Main Branch",
    "store_code": "MAIN",
    "store_phone": "555-0123",
    "store_address": "123 Main St"
  },
  "order": {
    "order_number": "ORD-20260127-001",
    "date_time": "2026-01-27 14:30:45",
    "cashier_name": "John",
    "status": "Completed"
  },
  "items": [
    {
      "sku": "SKU-001",
      "name": "Coca-Cola 1L",
      "quantity": 2,
      "unit_price": 2.50,
      "line_total": 5.00,
      "formatted_price": "2.50",
      "formatted_total": "5.00"
    }
  ],
  "totals": {
    "subtotal": 15.00,
    "discount": 0.00,
    "tax": 1.50,
    "total": 16.50
  },
  "payment": {
    "paid_amount": 16.50,
    "balance": 0.00,
    "payment_methods": [
      {
        "method": "Cash",
        "amount": 16.50,
        "count": 1
      }
    ]
  },
  "metadata": {
    "is_reprint": false,
    "item_count": 1,
    "printed_at": "2026-01-27 14:30:50"
  }
}
```

---

## Receipt Text Format (Thermal Printer)

```
                   MAIN STORE
                     MAIN
                --------------------------------
Order: ORD-20260127-001
Date: 01/27/2026 14:30
Cashier: John
--------------------------------
QTY  ITEM                    TOTAL
--------------------------------
  2  Coca-Cola 1L               5.00
     @ 2.50
  1  Orange Juice 1L            3.50
     @ 3.50
--------------------------------
                      Subtotal: 8.50
                           Tax: 0.85
=====================================
                       TOTAL: 9.35
=====================================

Payment:
                        Cash: 9.35

                    Thank You!
            2026-01-27 14:30:45
```

**Width:** 32 characters (58mm printer)

---

## Invoice JSON Structure

```json
{
  "invoice": {
    "invoice_number": "ORD-20260127-001",
    "invoice_date": "2026-01-27",
    "invoice_time": "14:30:45"
  },
  "merchant": {
    "name": "Main Branch",
    "code": "MAIN",
    "address": "123 Main St",
    "city": "Springfield",
    "state": "IL"
  },
  "transaction": {
    "cashier_id": 3,
    "cashier_name": "John Cashier",
    "order_status": "completed"
  },
  "items": [
    {
      "sku": "SKU-001",
      "product_name": "Coca-Cola 1L",
      "quantity": 2,
      "unit_price": 2.50,
      "cost_per_unit": 1.50,
      "line_subtotal": 5.00,
      "profit_per_unit": 1.00,
      "line_profit": 2.00
    }
  ],
  "financial_summary": {
    "subtotal": 15.00,
    "discount_amount": 0.00,
    "tax_amount": 1.50,
    "total": 16.50,
    "total_cost": 8.50,
    "gross_profit": 8.00
  },
  "payments": [
    {
      "method": "cash",
      "amount": 16.50,
      "timestamp": "2026-01-27T14:35:20Z",
      "status": "completed"
    }
  ]
}
```

---

## Quick Examples

### Get Receipt (Display)

```bash
curl http://localhost:8000/api/v1/orders/1/receipt \
  -H "Authorization: Bearer {token}"
```

### Get Receipt (Print)

```bash
curl http://localhost:8000/api/v1/orders/1/receipt/text \
  -H "Authorization: Bearer {token}"
# Send output directly to thermal printer
```

### Get Receipt (Browser)

```bash
curl http://localhost:8000/api/v1/orders/1/receipt/html \
  -H "Authorization: Bearer {token}"
# Open in browser or convert to PDF
```

### Reprint Receipt

```bash
curl -X POST http://localhost:8000/api/v1/orders/1/receipt/reprint \
  -H "Authorization: Bearer {token}"
```

### Get Invoice (JSON)

```bash
curl http://localhost:8000/api/v1/orders/1/invoice \
  -H "Authorization: Bearer {manager_token}"
```

### Download Invoice (CSV)

```bash
curl http://localhost:8000/api/v1/orders/1/invoice/csv \
  -H "Authorization: Bearer {manager_token}" \
  > invoice.csv
```

---

## Authorization

| Endpoint | Cashier | Manager | Admin |
|----------|---------|---------|-------|
| Receipt (JSON) | ✅ | ✅ | ✅ |
| Receipt (Text) | ✅ | ✅ | ✅ |
| Receipt (HTML) | ✅ | ✅ | ✅ |
| Receipt Reprint | ✅ | ✅ | ✅ |
| Invoice | ❌ | ✅ | ✅ |
| Invoice CSV | ❌ | ✅ | ✅ |
| Invoice JSON | ❌ | ✅ | ✅ |

---

## Common Use Cases

### Use Case 1: Print Receipt at POS
```javascript
// Get plain text, send to printer
const receipt = await api.getReceiptText(orderId);
printToPrinter(receipt);
```

### Use Case 2: Reprint Previous Order
```javascript
// Customer wants duplicate receipt
await api.reprintReceipt(orderId);
```

### Use Case 3: Export Invoice to Excel
```javascript
// Manager downloads order details
const csv = await api.downloadInvoiceCsv(orderId);
// Import into Excel/Sheets
```

### Use Case 4: Email Receipt
```javascript
// Send receipt to customer
const html = await api.getReceiptHtml(orderId);
sendEmail(customer.email, receipt: html);
```

### Use Case 5: Accounting Integration
```javascript
// Send invoice to QuickBooks
const invoice = await api.getInvoice(orderId);
quickbooks.post(invoice);
```

---

## Response Formats

### JSON Receipt (Receipt endpoint)
- Content-Type: `application/json`
- Structure: Hierarchical (header, order, items, totals, payment, metadata)
- Use: Frontend rendering, mobile apps

### Plain Text Receipt (Receipt/text endpoint)
- Content-Type: `text/plain`
- Structure: 32 character width
- Use: Direct to thermal printer, 58mm width
- Encoding: UTF-8

### HTML Receipt (Receipt/html endpoint)
- Content-Type: `text/html`
- Structure: Complete HTML document with CSS
- Use: Browser preview, PDF printing, email
- Print-friendly: CSS media queries

### Invoice JSON (Invoice endpoint)
- Content-Type: `application/json`
- Structure: Invoice + merchant + items + financials + payments
- Use: Accounting software, integration

### CSV Export (Invoice/csv endpoint)
- Content-Type: `text/csv`
- Structure: Multiple sections with headers
- Use: Excel/Sheets import, reporting

---

## Error Codes

| Code | Error | Meaning |
|------|-------|---------|
| 200 | OK | Success |
| 403 | Unauthorized | No permission or wrong branch |
| 403 | Forbidden | Role doesn't allow access (e.g., cashier to invoice) |
| 404 | Not Found | Order doesn't exist |
| 500 | Server Error | Formatting or data error |

---

## Performance

| Operation | Speed |
|-----------|-------|
| Get receipt JSON | <50ms |
| Get receipt text | <50ms |
| Get receipt HTML | <50ms |
| Get invoice | <100ms |
| CSV download | <75ms |

---

## Tips

✅ **Receipt Preview:** Use HTML endpoint in Electron app with print dialog  
✅ **Direct Printing:** Use text endpoint with raw printer commands  
✅ **Re-printing:** Endpoint tracks reprint flag for statistics  
✅ **Invoices:** Manager-only for financial/tax purposes  
✅ **CSV Export:** Open directly in Excel/Google Sheets  
✅ **Integration:** Use JSON endpoints for system-to-system transfers  

---

## Testing

```bash
# Test receipt access (cashier)
curl http://localhost:8000/api/v1/orders/1/receipt \
  -H "Authorization: Bearer {cashier_token}"

# Test invoice access (manager)
curl http://localhost:8000/api/v1/orders/1/invoice \
  -H "Authorization: Bearer {manager_token}"

# Test invoice denied (cashier)
curl http://localhost:8000/api/v1/orders/1/invoice \
  -H "Authorization: Bearer {cashier_token}"
# Response: 403 Forbidden
```

---

*Last Updated: January 27, 2026*
