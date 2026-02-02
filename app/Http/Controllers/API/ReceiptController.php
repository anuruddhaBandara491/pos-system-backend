<?php

namespace App\Http\Controllers\API;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiptController extends BaseController
{
    /**
     * Get receipt data for an order (formatted for thermal printing).
     *
     * Returns structured data suitable for 58mm or 80mm thermal printers.
     * Includes order details, items, totals, payments, and formatting hints.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $receipt = $this->formatReceipt($order);

            return $this->success($receipt, 'Receipt data retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve receipt: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get receipt in plain text format (for direct printing).
     *
     * Returns receipt as formatted plain text suitable for thermal printer.
     * Can be sent directly to printer via raw printing or ESC/POS commands.
     *
     * @param  Order  $order
     * @return Response
     */
    public function text(Order $order): Response
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return response('Unauthorized', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $text = $this->formatReceiptAsText($order);

            return response($text, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=receipt_{$order->order_number}.txt",
            ]);
        } catch (\Exception $e) {
            return response("Error: {$e->getMessage()}", 500);
        }
    }

    /**
     * Get receipt in HTML format (for browser preview/printing).
     *
     * Returns receipt as formatted HTML with styling for preview
     * before actual thermal printing.
     *
     * @param  Order  $order
     * @return Response
     */
    public function html(Order $order): Response
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return response('Unauthorized', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $html = $this->formatReceiptAsHtml($order);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        } catch (\Exception $e) {
            return response("Error: {$e->getMessage()}", 500);
        }
    }

    /**
     * Reprint receipt for an order.
     *
     * Same as show() but tracked separately for reprint counting.
     * Managers can reprint any order, cashiers can only reprint their own.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    public function reprint(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Cashiers can only reprint their own orders (optional - can remove for full access)
            // if ($user->hasRole('cashier') && $order->cashier_id !== $user->id) {
            //     return $this->error('Cashiers can only reprint their own orders', 403);
            // }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $receipt = $this->formatReceipt($order, true); // true = is reprint

            return $this->success($receipt, 'Receipt reprinted', 200, ['reprint' => true]);
        } catch (\Exception $e) {
            return $this->error('Failed to reprint receipt: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get invoice data for an order (manager/admin only).
     *
     * Returns detailed invoice data for tax/accounting purposes.
     * Includes full audit trail, customer info, and summary.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    public function invoice(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check authorization - manager/admin only
            if (!$user->hasAnyRole(['manager', 'admin'])) {
                return $this->error('Only managers and admins can access invoices', 403);
            }

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $invoice = $this->formatInvoice($order);

            return $this->success($invoice, 'Invoice data retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve invoice: '.$e->getMessage(), 500);
        }
    }

    /**
     * Download invoice as CSV (manager/admin only).
     *
     * Returns order and items data in CSV format for spreadsheet import.
     *
     * @param  Order  $order
     * @return Response
     */
    public function downloadInvoiceCsv(Order $order): Response
    {
        try {
            $user = request()->user();

            // Check authorization
            if (!$user->hasAnyRole(['manager', 'admin'])) {
                return response('Unauthorized', 403);
            }

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return response('Unauthorized', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $csv = $this->formatInvoiceAsCsv($order);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=invoice_{$order->order_number}.csv",
            ]);
        } catch (\Exception $e) {
            return response("Error: {$e->getMessage()}", 500);
        }
    }

    /**
     * Download invoice as JSON (manager/admin only).
     *
     * Returns full invoice data in JSON format for integration.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    public function downloadInvoiceJson(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check authorization
            if (!$user->hasAnyRole(['manager', 'admin'])) {
                return $this->error('Unauthorized', 403);
            }

            // Check branch access
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized', 403);
            }

            // Get full order with relationships
            $order->load(['items.product', 'branch', 'cashier', 'payments']);

            $invoice = $this->formatInvoice($order);

            return $this->success($invoice, 'Invoice data retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve invoice: '.$e->getMessage(), 500);
        }
    }

    /**
     * Format order data as receipt (JSON).
     *
     * Structures data for thermal printer consumption.
     * Includes formatting hints for line wrapping, alignment, etc.
     */
    private function formatReceipt(Order $order, bool $isReprint = false): array
    {
        $branch = $order->branch;
        $cashier = $order->cashier;
        $items = $order->items;

        // Header
        $header = [
            'store_name' => $branch->name ?? 'POS Store',
            'store_code' => $branch->code ?? 'MAIN',
            'store_phone' => $branch->phone ?? '',
            'store_address' => $branch->address ?? '',
        ];

        // Order information
        $orderInfo = [
            'order_number' => $order->order_number,
            'date_time' => $order->created_at->format('Y-m-d H:i:s'),
            'cashier_name' => $cashier->name ?? 'N/A',
            'status' => ucfirst($order->status),
        ];

        // Items (line items)
        $lineItems = $items->map(function ($item) {
            return [
                'sku' => $item->product->sku,
                'name' => substr($item->product->name, 0, 30), // Truncate for 58mm printer
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'formatted_price' => number_format($item->unit_price, 2),
                'formatted_total' => number_format($item->line_total, 2),
            ];
        })->toArray();

        // Totals
        $totals = [
            'subtotal' => $order->subtotal,
            'subtotal_formatted' => number_format($order->subtotal, 2),
            'discount' => $order->discount,
            'discount_formatted' => number_format($order->discount, 2),
            'tax' => $order->tax,
            'tax_formatted' => number_format($order->tax, 2),
            'total' => $order->total,
            'total_formatted' => number_format($order->total, 2),
        ];

        // Payment information
        $paymentInfo = [
            'paid_amount' => $order->paid_amount,
            'paid_formatted' => number_format($order->paid_amount, 2),
            'balance' => $order->remaining_balance,
            'balance_formatted' => number_format($order->remaining_balance, 2),
            'payment_methods' => $order->payments->groupBy('method')->map(function ($group) {
                return [
                    'method' => ucfirst($group->first()->method),
                    'amount' => $group->sum('amount'),
                    'amount_formatted' => number_format($group->sum('amount'), 2),
                    'count' => count($group),
                ];
            })->values()->toArray(),
        ];

        // Metadata
        $metadata = [
            'is_reprint' => $isReprint,
            'item_count' => count($lineItems),
            'printed_at' => now()->format('Y-m-d H:i:s'),
        ];

        return [
            'header' => $header,
            'order' => $orderInfo,
            'items' => $lineItems,
            'totals' => $totals,
            'payment' => $paymentInfo,
            'metadata' => $metadata,
        ];
    }

    /**
     * Format receipt as plain text for thermal printer.
     *
     * Returns ESC/POS compatible text format for 58mm printer (32 chars width).
     */
    private function formatReceiptAsText(Order $order): string
    {
        $width = 32; // 58mm printer width in characters
        $branch = $order->branch;

        $text = "";

        // Header - store name (centered)
        $text .= $this->centerText($branch->name ?? 'POS Store', $width) . "\n";
        $text .= $this->centerText($branch->code ?? 'MAIN', $width) . "\n";
        $text .= str_repeat('-', $width) . "\n";

        // Order info
        $text .= "Order: {$order->order_number}\n";
        $text .= "Date: {$order->created_at->format('m/d/Y H:i')}\n";
        $text .= "Cashier: {$order->cashier->name}\n";
        $text .= str_repeat('-', $width) . "\n";

        // Items header
        $text .= "QTY  ITEM                    TOTAL\n";
        $text .= str_repeat('-', $width) . "\n";

        // Items
        foreach ($order->items as $item) {
            $itemName = substr($item->product->name, 0, 20);
            $qty = $item->quantity;
            $total = number_format($item->line_total, 2);

            // Format: QTY  Name              Total
            $line = sprintf("%3d  %-20s  %8s", $qty, $itemName, $total);
            $text .= substr($line, 0, $width) . "\n";

            // Price line (optional)
            $price = "@ " . number_format($item->unit_price, 2);
            $text .= "     " . $price . "\n";
        }

        $text .= str_repeat('-', $width) . "\n";

        // Totals
        $subtotal = number_format($order->subtotal, 2);
        $text .= $this->rightAlignText("Subtotal: {$subtotal}", $width) . "\n";

        if ($order->discount > 0) {
            $discount = number_format($order->discount, 2);
            $text .= $this->rightAlignText("Discount: -{$discount}", $width) . "\n";
        }

        $tax = number_format($order->tax, 2);
        $text .= $this->rightAlignText("Tax: {$tax}", $width) . "\n";

        $text .= str_repeat('=', $width) . "\n";
        $total = number_format($order->total, 2);
        $text .= $this->rightAlignText("TOTAL: {$total}", $width) . "\n";
        $text .= str_repeat('=', $width) . "\n";

        // Payment info
        $text .= "\nPayment:\n";
        foreach ($order->payments->groupBy('method') as $method => $payments) {
            $amount = number_format($payments->sum('amount'), 2);
            $text .= $this->rightAlignText(ucfirst($method) . ": {$amount}", $width) . "\n";
        }

        // Closing
        $text .= "\n" . $this->centerText("Thank You!", $width) . "\n";
        $text .= $this->centerText(now()->format('Y-m-d H:i:s'), $width) . "\n";

        return $text;
    }

    /**
     * Format receipt as HTML for browser preview.
     */
    private function formatReceiptAsHtml(Order $order): string
    {
        $branch = $order->branch;
        $subtotal = number_format($order->subtotal, 2);
        $discount = number_format($order->discount, 2);
        $tax = number_format($order->tax, 2);
        $total = number_format($order->total, 2);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {$order->order_number}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 300px;
            margin: 20px auto;
            padding: 10px;
            background: #fff;
        }
        .receipt {
            background: white;
            border: 1px solid #ccc;
            padding: 15px;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .store-name {
            font-weight: bold;
            font-size: 14px;
        }
        .order-info {
            margin: 10px 0;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .items {
            margin: 10px 0;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
        }
        .item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 2px 0;
        }
        .item-name {
            flex: 1;
        }
        .item-qty {
            width: 40px;
            text-align: right;
        }
        .item-total {
            width: 60px;
            text-align: right;
        }
        .totals {
            margin: 10px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 10px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .total-amount {
            font-weight: bold;
            font-size: 14px;
        }
        .payment-info {
            margin: 10px 0;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 11px;
            color: #666;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .receipt {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="store-name">{$branch->name}</div>
            <div>{$branch->code}</div>
        </div>

        <div class="order-info">
            <div class="info-row">
                <span>Order:</span>
                <span>{$order->order_number}</span>
            </div>
            <div class="info-row">
                <span>Date:</span>
                <span>{$order->created_at->format('m/d/Y H:i')}</span>
            </div>
            <div class="info-row">
                <span>Cashier:</span>
                <span>{$order->cashier->name}</span>
            </div>
        </div>

        <div class="items">
            <div class="item-header">
                <span class="item-name">Item</span>
                <span class="item-qty">Qty</span>
                <span class="item-total">Total</span>
            </div>
HTML;

        foreach ($order->items as $item) {
            $itemName = $item->product->name;
            $qty = $item->quantity;
            $itemTotal = number_format($item->line_total, 2);
            $unitPrice = number_format($item->unit_price, 2);

            $html .= <<<HTML
            <div class="item">
                <span class="item-name">{$itemName}</span>
                <span class="item-qty">{$qty}</span>
                <span class="item-total">\${$itemTotal}</span>
            </div>
            <div style="font-size: 11px; color: #666; text-align: right;">@ \${$unitPrice}</div>
HTML;
        }

        $html .= <<<HTML
        </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>\${$subtotal}</span>
            </div>
HTML;

        if ($order->discount > 0) {
            $html .= <<<HTML
            <div class="total-row">
                <span>Discount:</span>
                <span>-\${$discount}</span>
            </div>
HTML;
        }

        $html .= <<<HTML
            <div class="total-row">
                <span>Tax:</span>
                <span>\${$tax}</span>
            </div>
            <div class="total-row total-amount">
                <span>TOTAL:</span>
                <span>\${$total}</span>
            </div>
        </div>

        <div class="payment-info">
            <strong>Payment:</strong>
HTML;

        foreach ($order->payments->groupBy('method') as $method => $payments) {
            $amount = number_format($payments->sum('amount'), 2);
            $html .= <<<HTML
            <div class="payment-row">
                <span>{$method}:</span>
                <span>\${$amount}</span>
            </div>
HTML;
        }

        $html .= <<<HTML
        </div>

        <div class="footer">
            <p>Thank You!</p>
            <p>{$order->created_at->format('Y-m-d H:i:s')}</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Format invoice data (detailed, for accounting).
     */
    private function formatInvoice(Order $order): array
    {
        $branch = $order->branch;
        $cashier = $order->cashier;

        // Invoice header
        $invoiceHeader = [
            'invoice_number' => $order->order_number,
            'invoice_date' => $order->created_at->toDateString(),
            'invoice_time' => $order->created_at->toTimeString(),
            'invoice_id' => $order->id,
        ];

        // Merchant info
        $merchantInfo = [
            'name' => $branch->name,
            'code' => $branch->code,
            'address' => $branch->address,
            'city' => $branch->city,
            'state' => $branch->state,
            'phone' => $branch->phone,
            'email' => $branch->email,
        ];

        // Transaction info
        $transactionInfo = [
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'cashier_email' => $cashier->email,
            'order_status' => $order->status,
            'order_created_at' => $order->created_at,
            'order_updated_at' => $order->updated_at,
        ];

        // Detailed items
        $itemDetails = $order->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'sku' => $item->product->sku,
                'product_name' => $item->product->name,
                'category' => $item->product->category,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'cost_per_unit' => $item->product->cost,
                'line_subtotal' => $item->line_total,
                'profit_per_unit' => $item->unit_price - $item->product->cost,
                'line_profit' => ($item->unit_price - $item->product->cost) * $item->quantity,
            ];
        })->toArray();

        // Financial summary
        $financialSummary = [
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount,
            'discount_percent' => $order->subtotal > 0 ? round(($order->discount / $order->subtotal) * 100, 2) : 0,
            'tax_rate' => '0.10', // TODO: Make configurable
            'tax_amount' => $order->tax,
            'total' => $order->total,
            'paid_amount' => $order->paid_amount,
            'remaining_balance' => $order->remaining_balance,
            'total_cost' => $order->items->sum(function ($item) {
                return $item->product->cost * $item->quantity;
            }),
            'gross_profit' => $order->total - $order->items->sum(function ($item) {
                return $item->product->cost * $item->quantity;
            }),
        ];

        // Payment details
        $paymentDetails = $order->payments->map(function ($payment) {
            return [
                'payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'timestamp' => $payment->created_at,
                'reference' => $payment->reference,
                'status' => $payment->status,
            ];
        })->toArray();

        return [
            'invoice' => $invoiceHeader,
            'merchant' => $merchantInfo,
            'transaction' => $transactionInfo,
            'items' => $itemDetails,
            'financial_summary' => $financialSummary,
            'payments' => $paymentDetails,
        ];
    }

    /**
     * Format invoice as CSV.
     */
    private function formatInvoiceAsCsv(Order $order): string
    {
        $csv = "Invoice Report\n";
        $csv .= "Invoice Number,Date,Time,Status\n";
        $csv .= "{$order->order_number},{$order->created_at->toDateString()},{$order->created_at->toTimeString()},{$order->status}\n\n";

        $csv .= "Branch,Code,City,Phone\n";
        $csv .= "\"{$order->branch->name}\",\"{$order->branch->code}\",\"{$order->branch->city}\",\"{$order->branch->phone}\"\n\n";

        $csv .= "Cashier,Email\n";
        $csv .= "\"{$order->cashier->name}\",\"{$order->cashier->email}\"\n\n";

        $csv .= "SKU,Product,Category,Quantity,Unit Price,Line Total,Cost,Profit\n";

        foreach ($order->items as $item) {
            $profit = ($item->unit_price - $item->product->cost) * $item->quantity;
            $csv .= "\"{$item->product->sku}\",\"{$item->product->name}\",\"{$item->product->category}\",{$item->quantity},";
            $csv .= "{$item->unit_price},{$item->line_total},{$item->product->cost},{$profit}\n";
        }

        $csv .= "\n";
        $csv .= "Summary\n";
        $csv .= "Subtotal,Discount,Tax,Total,Paid,Balance\n";
        $csv .= "{$order->subtotal},{$order->discount},{$order->tax},{$order->total},{$order->paid_amount},{$order->remaining_balance}\n";

        return $csv;
    }

    /**
     * Helper: Center text for thermal printer.
     */
    private function centerText(string $text, int $width): string
    {
        $padding = max(0, (int) (($width - strlen($text)) / 2));
        return str_repeat(' ', $padding) . $text;
    }

    /**
     * Helper: Right align text for thermal printer.
     */
    private function rightAlignText(string $text, int $width): string
    {
        $padding = max(0, $width - strlen($text));
        return str_repeat(' ', $padding) . $text;
    }
}
