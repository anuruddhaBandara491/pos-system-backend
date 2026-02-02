<?php

namespace App\Http\Controllers\API;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    /**
     * Daily sales report.
     *
     * Shows total sales, count, items sold, tax collected, and average transaction
     * for each day within the specified date range.
     *
     * Query Parameters:
     * - start_date: YYYY-MM-DD (required, default: today)
     * - end_date: YYYY-MM-DD (required, default: today)
     * - branch_id: integer (optional, manager+ only)
     *
     * @return JsonResponse
     */
    public function dailySales(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->query('start_date', today()->toDateString());
            $endDate = $request->query('end_date', today()->toDateString());
            $branchId = $request->query('branch_id');

            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format. Use YYYY-MM-DD', 400);
            }

            // Parse dates
            $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            // Validate date range
            if ($start > $end) {
                return $this->error('Start date cannot be after end date', 400);
            }

            // Check permission
            if ($branchId && $branchId != $user->branch_id && !$user->hasRole(['manager', 'admin'])) {
                return $this->error('You can only view your branch reports', 403);
            }

            // Build query
            $query = Order::whereBetween('created_at', [$start, $end])
                ->where('status', 'completed');

            // Apply branch filter
            if ($user->branch_id && !$user->hasRole('admin')) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($branchId) {
                $query->where('branch_id', $branchId);
            }

            // Get daily sales with grouping
            $dailySales = $query
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('COUNT(*) as transaction_count')
                ->selectRaw('SUM(total) as total_sales')
                ->selectRaw('SUM(subtotal) as subtotal')
                ->selectRaw('SUM(tax) as tax_collected')
                ->selectRaw('SUM(discount) as total_discount')
                ->selectRaw('AVG(total) as avg_transaction')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($day) {
                    return [
                        'date' => $day->date,
                        'transaction_count' => (int) $day->transaction_count,
                        'total_sales' => (float) $day->total_sales,
                        'total_sales_formatted' => number_format($day->total_sales, 2),
                        'subtotal' => (float) $day->subtotal,
                        'subtotal_formatted' => number_format($day->subtotal, 2),
                        'tax_collected' => (float) $day->tax_collected,
                        'tax_collected_formatted' => number_format($day->tax_collected, 2),
                        'total_discount' => (float) $day->total_discount,
                        'total_discount_formatted' => number_format($day->total_discount, 2),
                        'avg_transaction' => (float) $day->avg_transaction,
                        'avg_transaction_formatted' => number_format($day->avg_transaction, 2),
                    ];
                });

            // Calculate summary
            $summary = [
                'total_sales' => (float) $dailySales->sum('total_sales'),
                'total_transactions' => (int) $dailySales->sum('transaction_count'),
                'total_tax' => (float) $dailySales->sum('tax_collected'),
                'total_discount' => (float) $dailySales->sum('total_discount'),
                'days_in_range' => count($dailySales),
                'avg_daily_sales' => count($dailySales) > 0 ? $dailySales->sum('total_sales') / count($dailySales) : 0,
            ];

            return $this->success([
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'branch_id' => $branchId,
                ],
                'summary' => [
                    'total_sales' => (float) $summary['total_sales'],
                    'total_sales_formatted' => number_format($summary['total_sales'], 2),
                    'total_transactions' => $summary['total_transactions'],
                    'total_tax' => (float) $summary['total_tax'],
                    'total_tax_formatted' => number_format($summary['total_tax'], 2),
                    'total_discount' => (float) $summary['total_discount'],
                    'total_discount_formatted' => number_format($summary['total_discount'], 2),
                    'days_in_range' => $summary['days_in_range'],
                    'avg_daily_sales' => (float) $summary['avg_daily_sales'],
                    'avg_daily_sales_formatted' => number_format($summary['avg_daily_sales'], 2),
                ],
                'data' => $dailySales,
            ], 'Daily sales report retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve daily sales report: '.$e->getMessage(), 500);
        }
    }

    /**
     * Cashier-wise sales report.
     *
     * Shows sales, transaction count, and performance metrics for each cashier
     * within the specified date range.
     *
     * Query Parameters:
     * - start_date: YYYY-MM-DD (required, default: today)
     * - end_date: YYYY-MM-DD (required, default: today)
     * - branch_id: integer (optional, manager+ only)
     *
     * @return JsonResponse
     */
    public function cashierSales(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->query('start_date', today()->toDateString());
            $endDate = $request->query('end_date', today()->toDateString());
            $branchId = $request->query('branch_id');

            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format. Use YYYY-MM-DD', 400);
            }

            $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            if ($start > $end) {
                return $this->error('Start date cannot be after end date', 400);
            }

            // Check permission
            if ($branchId && $branchId != $user->branch_id && !$user->hasRole(['manager', 'admin'])) {
                return $this->error('You can only view your branch reports', 403);
            }

            // Build query
            $query = Order::whereBetween('created_at', [$start, $end])
                ->where('status', 'completed')
                ->join('users', 'orders.cashier_id', '=', 'users.id');

            // Apply branch filter
            if ($user->branch_id && !$user->hasRole('admin')) {
                $query->where('orders.branch_id', $user->branch_id);
            } elseif ($branchId) {
                $query->where('orders.branch_id', $branchId);
            }

            // Get cashier sales
            $cashierSales = $query
                ->selectRaw('users.id as cashier_id')
                ->selectRaw('users.name as cashier_name')
                ->selectRaw('COUNT(*) as transaction_count')
                ->selectRaw('SUM(orders.total) as total_sales')
                ->selectRaw('SUM(orders.subtotal) as subtotal')
                ->selectRaw('SUM(orders.tax) as tax_collected')
                ->selectRaw('SUM(orders.discount) as total_discount')
                ->selectRaw('AVG(orders.total) as avg_transaction')
                ->selectRaw('MIN(orders.total) as min_transaction')
                ->selectRaw('MAX(orders.total) as max_transaction')
                ->groupBy('users.id', 'users.name')
                ->orderBy('total_sales', 'desc')
                ->get()
                ->map(function ($cashier) {
                    return [
                        'cashier_id' => $cashier->cashier_id,
                        'cashier_name' => $cashier->cashier_name,
                        'transaction_count' => (int) $cashier->transaction_count,
                        'total_sales' => (float) $cashier->total_sales,
                        'total_sales_formatted' => number_format($cashier->total_sales, 2),
                        'subtotal' => (float) $cashier->subtotal,
                        'subtotal_formatted' => number_format($cashier->subtotal, 2),
                        'tax_collected' => (float) $cashier->tax_collected,
                        'tax_collected_formatted' => number_format($cashier->tax_collected, 2),
                        'total_discount' => (float) $cashier->total_discount,
                        'total_discount_formatted' => number_format($cashier->total_discount, 2),
                        'avg_transaction' => (float) $cashier->avg_transaction,
                        'avg_transaction_formatted' => number_format($cashier->avg_transaction, 2),
                        'min_transaction' => (float) $cashier->min_transaction,
                        'min_transaction_formatted' => number_format($cashier->min_transaction, 2),
                        'max_transaction' => (float) $cashier->max_transaction,
                        'max_transaction_formatted' => number_format($cashier->max_transaction, 2),
                    ];
                });

            return $this->success([
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'branch_id' => $branchId,
                ],
                'summary' => [
                    'total_cashiers' => count($cashierSales),
                    'total_sales' => (float) $cashierSales->sum('total_sales'),
                    'total_sales_formatted' => number_format($cashierSales->sum('total_sales'), 2),
                    'total_transactions' => (int) $cashierSales->sum('transaction_count'),
                ],
                'data' => $cashierSales->values(),
            ], 'Cashier sales report retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve cashier sales report: '.$e->getMessage(), 500);
        }
    }

    /**
     * Product-wise sales report.
     *
     * Shows sales, quantity, and performance metrics for each product
     * within the specified date range.
     *
     * Query Parameters:
     * - start_date: YYYY-MM-DD (required, default: today)
     * - end_date: YYYY-MM-DD (required, default: today)
     * - branch_id: integer (optional, manager+ only)
     * - category: string (optional)
     *
     * @return JsonResponse
     */
    public function productSales(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->query('start_date', today()->toDateString());
            $endDate = $request->query('end_date', today()->toDateString());
            $branchId = $request->query('branch_id');
            $category = $request->query('category');

            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format. Use YYYY-MM-DD', 400);
            }

            $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            if ($start > $end) {
                return $this->error('Start date cannot be after end date', 400);
            }

            // Check permission
            if ($branchId && $branchId != $user->branch_id && !$user->hasRole(['manager', 'admin'])) {
                return $this->error('You can only view your branch reports', 403);
            }

            // Build query
            $query = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->whereBetween('orders.created_at', [$start, $end])
                ->where('orders.status', 'completed');

            // Apply branch filter
            if ($user->branch_id && !$user->hasRole('admin')) {
                $query->where('orders.branch_id', $user->branch_id);
            } elseif ($branchId) {
                $query->where('orders.branch_id', $branchId);
            }

            // Apply category filter
            if ($category) {
                $query->where('products.category', $category);
            }

            // Get product sales
            $productSales = $query
                ->selectRaw('products.id as product_id')
                ->selectRaw('products.sku as sku')
                ->selectRaw('products.name as product_name')
                ->selectRaw('products.category as category')
                ->selectRaw('products.price as current_price')
                ->selectRaw('SUM(order_items.quantity) as quantity_sold')
                ->selectRaw('SUM(order_items.line_total) as total_sales')
                ->selectRaw('AVG(order_items.unit_price) as avg_price')
                ->selectRaw('MIN(order_items.unit_price) as min_price')
                ->selectRaw('MAX(order_items.unit_price) as max_price')
                ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
                ->groupBy('products.id', 'products.sku', 'products.name', 'products.category', 'products.price')
                ->orderBy('total_sales', 'desc')
                ->get()
                ->map(function ($product) {
                    return [
                        'product_id' => $product->product_id,
                        'sku' => $product->sku,
                        'product_name' => $product->product_name,
                        'category' => $product->category,
                        'quantity_sold' => (int) $product->quantity_sold,
                        'total_sales' => (float) $product->total_sales,
                        'total_sales_formatted' => number_format($product->total_sales, 2),
                        'avg_price' => (float) $product->avg_price,
                        'avg_price_formatted' => number_format($product->avg_price, 2),
                        'min_price' => (float) $product->min_price,
                        'min_price_formatted' => number_format($product->min_price, 2),
                        'max_price' => (float) $product->max_price,
                        'max_price_formatted' => number_format($product->max_price, 2),
                        'current_price' => (float) $product->current_price,
                        'current_price_formatted' => number_format($product->current_price, 2),
                        'orders_count' => (int) $product->orders_count,
                    ];
                });

            return $this->success([
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'branch_id' => $branchId,
                    'category' => $category,
                ],
                'summary' => [
                    'total_products' => count($productSales),
                    'total_quantity_sold' => (int) $productSales->sum('quantity_sold'),
                    'total_sales' => (float) $productSales->sum('total_sales'),
                    'total_sales_formatted' => number_format($productSales->sum('total_sales'), 2),
                ],
                'data' => $productSales->values(),
            ], 'Product sales report retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product sales report: '.$e->getMessage(), 500);
        }
    }

    /**
     * Profit report.
     *
     * Shows detailed profit analysis including cost, revenue, and profit margins
     * by product or date range.
     *
     * Query Parameters:
     * - start_date: YYYY-MM-DD (required, default: today)
     * - end_date: YYYY-MM-DD (required, default: today)
     * - branch_id: integer (optional, manager+ only)
     * - group_by: 'product' or 'date' (default: 'product')
     *
     * @return JsonResponse
     */
    public function profitReport(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check permission (profit reports are manager+ only)
            if (!$user->hasRole(['manager', 'admin'])) {
                return $this->error('You do not have permission to view profit reports', 403);
            }

            $startDate = $request->query('start_date', today()->toDateString());
            $endDate = $request->query('end_date', today()->toDateString());
            $branchId = $request->query('branch_id');
            $groupBy = $request->query('group_by', 'product');

            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format. Use YYYY-MM-DD', 400);
            }

            $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            if ($start > $end) {
                return $this->error('Start date cannot be after end date', 400);
            }

            // Validate group_by parameter
            if (!in_array($groupBy, ['product', 'date'])) {
                return $this->error('group_by must be either "product" or "date"', 400);
            }

            // Check permission for branch filter
            if ($branchId && $branchId != $user->branch_id && !$user->hasRole('admin')) {
                return $this->error('You can only view your branch reports', 403);
            }

            // Note: For cost calculation, we need product cost at time of sale
            // This assumes products have current cost field (we'll use that as proxy)
            // In real scenario, might store cost with each order item

            if ($groupBy === 'product') {
                $profitData = $this->getProfitByProduct($start, $end, $user, $branchId);
            } else {
                $profitData = $this->getProfitByDate($start, $end, $user, $branchId);
            }

            return $this->success([
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'branch_id' => $branchId,
                    'group_by' => $groupBy,
                ],
                'summary' => [
                    'total_revenue' => (float) $profitData['summary']['total_revenue'],
                    'total_revenue_formatted' => number_format($profitData['summary']['total_revenue'], 2),
                    'total_cost' => (float) $profitData['summary']['total_cost'],
                    'total_cost_formatted' => number_format($profitData['summary']['total_cost'], 2),
                    'total_profit' => (float) $profitData['summary']['total_profit'],
                    'total_profit_formatted' => number_format($profitData['summary']['total_profit'], 2),
                    'profit_margin_percent' => (float) $profitData['summary']['profit_margin_percent'],
                ],
                'data' => $profitData['data'],
            ], 'Profit report retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve profit report: '.$e->getMessage(), 500);
        }
    }

    /**
     * Helper: Get profit by product.
     */
    private function getProfitByProduct(Carbon $start, Carbon $end, $user, $branchId = null)
    {
        $query = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', 'completed');

        if ($user->branch_id && !$user->hasRole('admin')) {
            $query->where('orders.branch_id', $user->branch_id);
        } elseif ($branchId) {
            $query->where('orders.branch_id', $branchId);
        }

        $data = $query
            ->selectRaw('products.id as product_id')
            ->selectRaw('products.sku as sku')
            ->selectRaw('products.name as product_name')
            ->selectRaw('SUM(order_items.line_total) as total_revenue')
            ->selectRaw('SUM(order_items.quantity * products.cost) as total_cost')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($product) {
                $revenue = (float) $product->total_revenue;
                $cost = (float) $product->total_cost;
                $profit = $revenue - $cost;
                $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

                return [
                    'product_id' => $product->product_id,
                    'sku' => $product->sku,
                    'product_name' => $product->product_name,
                    'quantity_sold' => (int) $product->quantity_sold,
                    'orders_count' => (int) $product->orders_count,
                    'total_revenue' => $revenue,
                    'total_revenue_formatted' => number_format($revenue, 2),
                    'total_cost' => $cost,
                    'total_cost_formatted' => number_format($cost, 2),
                    'total_profit' => $profit,
                    'total_profit_formatted' => number_format($profit, 2),
                    'profit_margin_percent' => $margin,
                ];
            });

        $totalRevenue = $data->sum('total_revenue');
        $totalCost = $data->sum('total_cost');
        $totalProfit = $totalRevenue - $totalCost;
        $marginPercent = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'data' => $data->values(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin_percent' => $marginPercent,
            ],
        ];
    }

    /**
     * Helper: Get profit by date.
     */
    private function getProfitByDate(Carbon $start, Carbon $end, $user, $branchId = null)
    {
        $query = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', 'completed');

        if ($user->branch_id && !$user->hasRole('admin')) {
            $query->where('orders.branch_id', $user->branch_id);
        } elseif ($branchId) {
            $query->where('orders.branch_id', $branchId);
        }

        $data = $query
            ->selectRaw('DATE(orders.created_at) as date')
            ->selectRaw('SUM(order_items.line_total) as total_revenue')
            ->selectRaw('SUM(order_items.quantity * products.cost) as total_cost')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($day) {
                $revenue = (float) $day->total_revenue;
                $cost = (float) $day->total_cost;
                $profit = $revenue - $cost;
                $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

                return [
                    'date' => $day->date,
                    'orders_count' => (int) $day->orders_count,
                    'quantity_sold' => (int) $day->quantity_sold,
                    'total_revenue' => $revenue,
                    'total_revenue_formatted' => number_format($revenue, 2),
                    'total_cost' => $cost,
                    'total_cost_formatted' => number_format($cost, 2),
                    'total_profit' => $profit,
                    'total_profit_formatted' => number_format($profit, 2),
                    'profit_margin_percent' => $margin,
                ];
            });

        $totalRevenue = $data->sum('total_revenue');
        $totalCost = $data->sum('total_cost');
        $totalProfit = $totalRevenue - $totalCost;
        $marginPercent = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'data' => $data->values(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin_percent' => $marginPercent,
            ],
        ];
    }

    /**
     * Helper: Validate date format (YYYY-MM-DD).
     */
    private function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            && strtotime($date) !== false;
    }
}
