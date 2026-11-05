<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Support\ReportFilters;

/**
 * Owner dashboard. Every figure comes from the database; sales figures reuse
 * ReportService so the dashboard and the reports never disagree.
 */
class DashboardService
{
    private const CHART_DAYS = 14;

    public function __construct(
        private InventoryService $inventory,
        private ReportService $reports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = new ReportFilters(now()->startOfDay(), now()->startOfDay());
        $month = new ReportFilters(now()->startOfMonth(), now()->startOfDay());

        $monthProfit = $this->reports->grossProfit($month)['summary'];

        return [
            'todayRevenue' => (float) $this->reports->paidOrders($today)->sum('grand_total'),
            'todayTransactions' => $this->reports->paidOrders($today)->count(),
            'monthRevenue' => (float) $this->reports->paidOrders($month)->sum('grand_total'),
            'monthGrossProfit' => (float) $monthProfit[2]['value'],
            'monthMargin' => (float) $monthProfit[3]['value'],
            'inventoryValue' => $this->inventory->inventoryValue(),
            'lowStockCount' => Ingredient::lowStock()->count(),
            'purchasesThisMonth' => (float) Purchase::query()
                ->whereBetween('purchased_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total'),
            'bestSellers' => OrderItem::query()
                ->whereIn('order_id', $this->reports->paidOrders($month)->select('orders.id'))
                ->selectRaw('product_id, MAX(product_name) as name, SUM(quantity) as qty, SUM(subtotal) as sales')
                ->groupBy('product_id')
                ->orderByDesc('qty')
                ->limit(5)
                ->get(),
            'chart' => $this->dailySales(),
            'recentOrders' => Order::with(['user', 'payment'])
                ->where('status', OrderStatus::Paid)
                ->latest('paid_at')
                ->limit(6)
                ->get(),
            'productsWithoutRecipe' => Product::query()
                ->whereDoesntHave('recipe.items')
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name']),
            'lowStockIngredients' => Ingredient::lowStock()
                ->orderByRaw('stock / NULLIF(min_stock, 0)')
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * Revenue per day for the last CHART_DAYS days, with empty days as 0.
     *
     * @return array{labels: list<string>, revenue: list<float>, transactions: list<int>}
     */
    public function dailySales(): array
    {
        $from = now()->subDays(self::CHART_DAYS - 1)->startOfDay();
        $filters = new ReportFilters($from, now()->startOfDay());

        $rows = $this->reports->paidOrders($filters)
            ->selectRaw('DATE(paid_at) as day, SUM(grand_total) as revenue, COUNT(*) as transactions')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $labels = $revenue = $transactions = [];

        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->translatedFormat('d M');
            $revenue[] = (float) ($rows[$key]->revenue ?? 0);
            $transactions[] = (int) ($rows[$key]->transactions ?? 0);
        }

        return compact('labels', 'revenue', 'transactions');
    }
}
