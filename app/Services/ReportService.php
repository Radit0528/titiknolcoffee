<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMutationType;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Models\StockMutation;
use App\Models\User;
use App\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owner reports. Every report is built from actual database rows and returned
 * in one shape, so the web page, the PDF and the Excel file always agree:
 *
 *   [
 *     'title'    => 'Laporan Penjualan',
 *     'summary'  => [['label' => 'Total omzet', 'value' => 123, 'type' => 'money'], ...],
 *     'notes'    => ['How a figure is computed', ...],
 *     'sections' => [[
 *         'title'   => 'Per hari',
 *         'columns' => [['label' => 'Tanggal', 'type' => 'date'], ...],
 *         'rows'    => [[...], ...],
 *         'totals'  => [...] | null,
 *     ], ...],
 *   ]
 *
 * Column types: text, int, money, qty, percent, date, datetime.
 */
class ReportService
{
    public const TYPES = [
        'penjualan' => 'Penjualan',
        'laba-kotor' => 'Laba Kotor',
        'bahan-baku' => 'Bahan Baku',
        'shift' => 'Shift',
    ];

    public function __construct(private ShiftService $shifts) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $type, ReportFilters $filters): array
    {
        return match ($type) {
            'penjualan' => $this->sales($filters),
            'laba-kotor' => $this->grossProfit($filters),
            'bahan-baku' => $this->ingredients($filters),
            'shift' => $this->shiftReport($filters),
        };
    }

    /**
     * Paid orders in the period, optionally for one cashier / payment method.
     */
    public function paidOrders(ReportFilters $filters): Builder
    {
        return Order::query()
            ->where('orders.status', OrderStatus::Paid)
            ->whereBetween('orders.paid_at', [$filters->start(), $filters->end()])
            ->when($filters->cashierId, fn ($q, $id) => $q->where('orders.user_id', $id))
            ->when($filters->method, fn ($q, $method) => $q->whereHas('payments', fn ($p) => $p->where('method', $method)->where('status', 'paid')));
    }

    /**
     * Laporan penjualan: omzet, transaksi, produk terjual, per hari / produk / metode.
     *
     * @return array<string, mixed>
     */
    public function sales(ReportFilters $filters): array
    {
        $orders = $this->paidOrders($filters);
        $orderIds = (clone $orders)->select('orders.id');

        $revenue = (float) (clone $orders)->sum('grand_total');
        $count = (clone $orders)->count();
        $items = (int) OrderItem::whereIn('order_id', $orderIds)->sum('quantity');

        $daily = (clone $orders)
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as transactions, SUM(grand_total) as revenue, SUM(discount) as discount')
            ->groupBy('day')->orderBy('day')->get()
            ->keyBy('day');
        $dailyItems = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->selectRaw('DATE(orders.paid_at) as day, SUM(order_items.quantity) as items')
            ->groupBy('day')->pluck('items', 'day');

        $products = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_id, MAX(product_name) as name, SUM(quantity) as qty, SUM(subtotal) as sales')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->get();

        $methods = DB::table('payments')
            ->whereIn('order_id', $orderIds)
            ->where('status', 'paid')
            ->selectRaw('method, COUNT(*) as transactions, SUM(amount) as total')
            ->groupBy('method')->get();

        return [
            'title' => 'Laporan Penjualan',
            'summary' => [
                ['label' => 'Total omzet', 'value' => $revenue, 'type' => 'money'],
                ['label' => 'Jumlah transaksi', 'value' => $count, 'type' => 'int'],
                ['label' => 'Produk terjual', 'value' => $items, 'type' => 'int'],
                ['label' => 'Rata-rata per transaksi', 'value' => $count ? $revenue / $count : 0, 'type' => 'money'],
            ],
            'notes' => ['Omzet = total yang dibayar pelanggan (setelah diskon, termasuk pajak, service charge, dan pembulatan) dari transaksi lunas.'],
            'sections' => [
                [
                    'title' => 'Per hari',
                    'columns' => [
                        ['label' => 'Tanggal', 'type' => 'date'],
                        ['label' => 'Transaksi', 'type' => 'int'],
                        ['label' => 'Item', 'type' => 'int'],
                        ['label' => 'Diskon', 'type' => 'money'],
                        ['label' => 'Omzet', 'type' => 'money'],
                    ],
                    'rows' => $daily->map(fn ($d) => [
                        $d->day, (int) $d->transactions, (int) ($dailyItems[$d->day] ?? 0), (float) $d->discount, (float) $d->revenue,
                    ])->values()->all(),
                    'totals' => ['Total', $count, $items, (float) (clone $orders)->sum('discount'), $revenue],
                ],
                [
                    'title' => 'Per produk',
                    'columns' => [
                        ['label' => 'Produk', 'type' => 'text'],
                        ['label' => 'Terjual', 'type' => 'int'],
                        ['label' => 'Penjualan (sebelum diskon)', 'type' => 'money'],
                    ],
                    'rows' => $products->map(fn ($p) => [$p->name, (int) $p->qty, (float) $p->sales])->all(),
                    'totals' => ['Total', $items, (float) $products->sum('sales')],
                ],
                [
                    'title' => 'Per metode pembayaran',
                    'columns' => [
                        ['label' => 'Metode', 'type' => 'text'],
                        ['label' => 'Transaksi', 'type' => 'int'],
                        ['label' => 'Total', 'type' => 'money'],
                    ],
                    'rows' => $methods->map(fn ($m) => [PaymentMethod::from($m->method)->label(), (int) $m->transactions, (float) $m->total])->all(),
                    'totals' => null,
                ],
            ],
        ];
    }

    /**
     * Laporan laba kotor.
     *
     *   pendapatan  = subtotal − diskon + service charge   (pajak bukan pendapatan toko)
     *   laba kotor  = pendapatan − HPP terjual (snapshot saat transaksi)
     *   laba bersih bahan = laba kotor − nilai bahan terbuang (rusak/kedaluwarsa/tumpah)
     *
     * @return array<string, mixed>
     */
    public function grossProfit(ReportFilters $filters): array
    {
        $orders = $this->paidOrders($filters);
        $totals = (clone $orders)->selectRaw(
            'COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(service_charge),0) as service, '.
            'COALESCE(SUM(tax),0) as tax, COALESCE(SUM(rounding),0) as rounding, COALESCE(SUM(hpp_total),0) as hpp'
        )->first();

        $netSales = (float) $totals->subtotal - (float) $totals->discount;
        $revenue = $netSales + (float) $totals->service;
        $hpp = (float) $totals->hpp;
        $grossProfit = $revenue - $hpp;
        $waste = $this->wasteValue($filters);

        $products = OrderItem::query()
            ->whereIn('order_id', (clone $orders)->select('orders.id'))
            ->selectRaw('product_id, MAX(product_name) as name, SUM(quantity) as qty, SUM(subtotal) as sales, SUM(unit_hpp * quantity) as hpp')
            ->groupBy('product_id')
            ->orderByRaw('SUM(subtotal) - SUM(unit_hpp * quantity) DESC')
            ->get();

        return [
            'title' => 'Laporan Laba Kotor',
            'summary' => [
                ['label' => 'Pendapatan', 'value' => $revenue, 'type' => 'money'],
                ['label' => 'Total HPP', 'value' => $hpp, 'type' => 'money'],
                ['label' => 'Laba kotor', 'value' => $grossProfit, 'type' => 'money'],
                ['label' => 'Margin', 'value' => $revenue > 0 ? $grossProfit / $revenue * 100 : 0, 'type' => 'percent'],
            ],
            'notes' => [
                'Pendapatan = subtotal − diskon + service charge. Pajak tidak dihitung sebagai pendapatan karena disetor ke negara.',
                'HPP diambil dari snapshot saat transaksi, sehingga perubahan harga bahan setelahnya tidak mengubah laporan lama.',
                'Laba per produk dihitung sebelum diskon transaksi.',
            ],
            'sections' => [
                [
                    'title' => 'Rincian',
                    'columns' => [['label' => 'Komponen', 'type' => 'text'], ['label' => 'Nilai', 'type' => 'money']],
                    'rows' => [
                        ['Penjualan kotor (subtotal)', (float) $totals->subtotal],
                        ['Diskon', -(float) $totals->discount],
                        ['Service charge', (float) $totals->service],
                        ['Pendapatan', $revenue],
                        ['HPP terjual', -$hpp],
                        ['Laba kotor', $grossProfit],
                        ['Bahan terbuang (rusak/kedaluwarsa/tumpah)', -$waste],
                        ['Laba kotor setelah bahan terbuang', $grossProfit - $waste],
                        ['Pajak terkumpul (titipan, bukan pendapatan)', (float) $totals->tax],
                        ['Pembulatan tunai', (float) $totals->rounding],
                    ],
                    'totals' => null,
                ],
                [
                    'title' => 'Per produk',
                    'columns' => [
                        ['label' => 'Produk', 'type' => 'text'],
                        ['label' => 'Terjual', 'type' => 'int'],
                        ['label' => 'Penjualan', 'type' => 'money'],
                        ['label' => 'HPP', 'type' => 'money'],
                        ['label' => 'Laba kotor', 'type' => 'money'],
                        ['label' => 'Margin', 'type' => 'percent'],
                    ],
                    'rows' => $products->map(fn ($p) => [
                        $p->name, (int) $p->qty, (float) $p->sales, (float) $p->hpp,
                        (float) $p->sales - (float) $p->hpp,
                        (float) $p->sales > 0 ? ((float) $p->sales - (float) $p->hpp) / (float) $p->sales * 100 : 0,
                    ])->all(),
                    'totals' => [
                        'Total', (int) $products->sum('qty'), (float) $products->sum('sales'), (float) $products->sum('hpp'),
                        (float) $products->sum('sales') - (float) $products->sum('hpp'), null,
                    ],
                ],
            ],
        ];
    }

    /**
     * Laporan bahan baku. Every stock change is in the mutation ledger, so:
     *
     *   stok awal = Σ mutasi sebelum periode
     *   stok akhir = stok awal + masuk − terpakai − keluar manual ± penyesuaian
     *
     * @return array<string, mixed>
     */
    public function ingredients(ReportFilters $filters): array
    {
        $start = $filters->start();
        $end = $filters->end();

        $opening = StockMutation::where('created_at', '<', $start)
            ->selectRaw('ingredient_id, SUM(quantity) as qty')
            ->groupBy('ingredient_id')->pluck('qty', 'ingredient_id');

        $period = StockMutation::whereBetween('created_at', [$start, $end])
            ->selectRaw('ingredient_id, type, SUM(quantity) as qty, SUM(ABS(quantity) * cost_per_unit) as value')
            ->groupBy('ingredient_id', 'type')->get()
            ->groupBy('ingredient_id');

        $sum = fn (Collection $rows, array $types, string $field = 'qty') => (float) $rows
            ->filter(fn ($r) => in_array($r->type instanceof StockMutationType ? $r->type : StockMutationType::tryFrom($r->type), $types, true))
            ->sum($field);

        $in = [StockMutationType::Initial, StockMutationType::Purchase];
        // A void puts sold stock back, so it nets against usage.
        $used = [StockMutationType::Sale, StockMutationType::VoidReturn];
        $wasted = StockMutationType::stockOutTypes();
        $adjusted = array_values(array_filter(StockMutationType::cases(), fn ($t) => ! in_array($t, [...$in, ...$used, ...$wasted], true)));

        $ingredients = Ingredient::withTrashed()
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereIn('id', $period->keys()))
            ->orderBy('name')
            ->get();

        $rows = $ingredients->map(function (Ingredient $i) use ($opening, $period, $sum, $in, $used, $wasted, $adjusted) {
            $mut = $period->get($i->id, collect());
            $open = (float) ($opening[$i->id] ?? 0);
            $qtyIn = $sum($mut, $in);
            $qtyUsed = -$sum($mut, $used);
            $qtyWaste = -$sum($mut, $wasted);
            $qtyAdjust = $sum($mut, $adjusted);

            return [
                $i->name.($i->trashed() ? ' (dihapus)' : ''),
                $i->unit->label(),
                $open,
                $qtyIn,
                $qtyUsed,
                $qtyWaste,
                $qtyAdjust,
                $open + $qtyIn - $qtyUsed - $qtyWaste + $qtyAdjust,
                $sum($mut, $wasted, 'value'),
            ];
        });

        return [
            'title' => 'Laporan Bahan Baku',
            'summary' => [
                ['label' => 'Jenis bahan', 'value' => $rows->count(), 'type' => 'int'],
                ['label' => 'Nilai bahan terbuang', 'value' => (float) $rows->sum(8), 'type' => 'money'],
                ['label' => 'Nilai pembelian', 'value' => $this->purchaseValue($filters), 'type' => 'money'],
            ],
            'notes' => [
                'Stok akhir = stok awal + masuk − terpakai − keluar manual ± penyesuaian. Semua angka berasal dari mutasi stok.',
                'Masuk = stok awal bahan baru + pembelian. Terpakai = penjualan. Keluar manual = rusak, kedaluwarsa, tumpah.',
            ],
            'sections' => [[
                'title' => 'Mutasi per bahan',
                'columns' => [
                    ['label' => 'Bahan', 'type' => 'text'],
                    ['label' => 'Satuan', 'type' => 'text'],
                    ['label' => 'Stok awal', 'type' => 'qty'],
                    ['label' => 'Masuk', 'type' => 'qty'],
                    ['label' => 'Terpakai', 'type' => 'qty'],
                    ['label' => 'Keluar manual', 'type' => 'qty'],
                    ['label' => 'Penyesuaian', 'type' => 'qty'],
                    ['label' => 'Stok akhir', 'type' => 'qty'],
                    ['label' => 'Nilai terbuang', 'type' => 'money'],
                ],
                'rows' => $rows->all(),
                'totals' => null,
            ]],
        ];
    }

    /**
     * Laporan shift: one row per shift opened in the period.
     *
     * @return array<string, mixed>
     */
    public function shiftReport(ReportFilters $filters): array
    {
        $shifts = Shift::with('user')
            ->whereBetween('opened_at', [$filters->start(), $filters->end()])
            ->when($filters->cashierId, fn ($q, $id) => $q->where('user_id', $id))
            ->orderBy('opened_at')
            ->get();

        $rows = $shifts->map(function (Shift $shift) {
            $s = $this->shifts->summary($shift);

            return [
                $shift->user->name,
                $shift->opened_at->format('Y-m-d H:i'),
                $shift->closed_at?->format('Y-m-d H:i'),
                $s['opening_cash'],
                $s['cash_sales'],
                $s['qris_sales'],
                $s['cash_in'],
                $s['cash_out'],
                $shift->closed_at ? (float) $shift->expected_cash : $s['expected_cash'],
                $shift->closed_at ? (float) $shift->closing_cash : null,
                $shift->closed_at ? (float) $shift->cash_variance : null,
            ];
        });

        $closed = $shifts->whereNotNull('closed_at');

        return [
            'title' => 'Laporan Shift',
            'summary' => [
                ['label' => 'Jumlah shift', 'value' => $shifts->count(), 'type' => 'int'],
                ['label' => 'Shift berselisih', 'value' => $closed->filter(fn ($s) => (float) $s->cash_variance != 0)->count(), 'type' => 'int'],
                ['label' => 'Total selisih kas', 'value' => (float) $closed->sum('cash_variance'), 'type' => 'money'],
            ],
            'notes' => ['Uang seharusnya = modal awal + penjualan tunai + kas masuk − kas keluar. Selisih = uang fisik − uang seharusnya.'],
            'sections' => [[
                'title' => 'Daftar shift',
                'columns' => [
                    ['label' => 'Kasir', 'type' => 'text'],
                    ['label' => 'Buka', 'type' => 'datetime'],
                    ['label' => 'Tutup', 'type' => 'datetime'],
                    ['label' => 'Modal awal', 'type' => 'money'],
                    ['label' => 'Tunai', 'type' => 'money'],
                    ['label' => 'QRIS', 'type' => 'money'],
                    ['label' => 'Kas masuk', 'type' => 'money'],
                    ['label' => 'Kas keluar', 'type' => 'money'],
                    ['label' => 'Seharusnya', 'type' => 'money'],
                    ['label' => 'Uang fisik', 'type' => 'money'],
                    ['label' => 'Selisih', 'type' => 'money'],
                ],
                'rows' => $rows->all(),
                'totals' => [
                    'Total', null, null, (float) $rows->sum(3), (float) $rows->sum(4), (float) $rows->sum(5),
                    (float) $rows->sum(6), (float) $rows->sum(7), null, null, (float) $closed->sum('cash_variance'),
                ],
            ]],
        ];
    }

    /**
     * Value of stock written off as damaged/expired/spilled in the period.
     */
    public function wasteValue(ReportFilters $filters): float
    {
        return (float) StockMutation::whereBetween('created_at', [$filters->start(), $filters->end()])
            ->whereIn('type', array_map(fn ($t) => $t->value, StockMutationType::stockOutTypes()))
            ->sum(DB::raw('ABS(quantity) * cost_per_unit'));
    }

    private function purchaseValue(ReportFilters $filters): float
    {
        return (float) DB::table('purchases')
            ->whereBetween('purchased_at', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->sum('total');
    }

    /**
     * Cashiers for the filter dropdown (including deactivated ones).
     *
     * @return Collection<int, User>
     */
    public function cashiers(): Collection
    {
        return User::where('role', 'cashier')->orderBy('name')->get(['id', 'name']);
    }
}
