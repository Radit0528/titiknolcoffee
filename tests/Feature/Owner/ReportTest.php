<?php

namespace Tests\Feature\Owner;

use App\Enums\StockMutationType;
use App\Exports\ReportExport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Voucher;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SettingService;
use App\Services\ShiftService;
use App\Support\ReportFilters;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Cashier\PosTestCase;

/**
 * Figures below are worked out by hand from the PosTestCase fixture:
 * Kopi Susu Aren Rp22.000, HPP Rp7.500 (beans 18 g, milk 120 ml, aren 20 ml).
 */
class ReportTest extends PosTestCase
{
    private function period(string $from = '2026-11-01', string $to = '2026-11-30'): ReportFilters
    {
        return new ReportFilters(Carbon::parse($from), Carbon::parse($to));
    }

    /**
     * 1 Nov: 2 cups cash, no discount            → subtotal 44.000
     * 2 Nov: 1 cup cash with voucher Rp2.000     → subtotal 22.000, discount 2.000
     * 2 Nov: waste 100 ml milk @ Rp20            → Rp2.000
     * 31 Okt (outside period): 1 cup             → must be ignored
     */
    private function seedSales(): void
    {
        Voucher::factory()->fixed(2000)->create(['code' => 'HEMAT']);

        Carbon::setTestNow('2026-10-31 10:00');
        $this->checkout($this->cart(1, ['cash_received' => 100000]))->assertOk();

        Carbon::setTestNow('2026-11-01 09:00');
        $this->checkout($this->cart(2, ['cash_received' => 100000]))->assertOk();

        Carbon::setTestNow('2026-11-02 15:00');
        $this->checkout($this->cart(1, ['voucher_code' => 'HEMAT', 'cash_received' => 100000]))->assertOk();
        $this->actingAs($this->owner());
        app(InventoryService::class)->stockOut($this->ing['milk'], StockMutationType::Expired, 100, 'Basi');

        Carbon::setTestNow('2026-11-03 12:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sales_report_figures(): void
    {
        $this->seedSales();
        $report = app(ReportService::class)->sales($this->period());

        $this->assertSame(64000.0, $report['summary'][0]['value'], 'omzet 44.000 + 20.000');
        $this->assertSame(2, $report['summary'][1]['value']);
        $this->assertSame(3, $report['summary'][2]['value']);

        [$daily, $products, $methods] = $report['sections'];
        $this->assertSame([['2026-11-01', 1, 2, 0.0, 44000.0], ['2026-11-02', 1, 1, 2000.0, 20000.0]], $daily['rows']);
        $this->assertSame([['Kopi Susu Aren', 3, 66000.0]], $products['rows']);
        $this->assertSame([['Tunai', 2, 64000.0]], $methods['rows']);
    }

    public function test_gross_profit_report_figures(): void
    {
        app(SettingService::class)->update(['tax_percent' => 10]);
        $this->seedSales();
        $report = app(ReportService::class)->grossProfit($this->period());

        // pendapatan = 66.000 − 2.000 = 64.000 (pajak tidak dihitung)
        // HPP = 3 × 7.500 = 22.500 → laba kotor 41.500
        $this->assertSame(64000.0, $report['summary'][0]['value']);
        $this->assertSame(22500.0, $report['summary'][1]['value']);
        $this->assertSame(41500.0, $report['summary'][2]['value']);

        $rows = collect($report['sections'][0]['rows'])->pluck(1, 0);
        $this->assertSame(-2000.0, $rows['Bahan terbuang (rusak/kedaluwarsa/tumpah)'], '100 ml × Rp20');
        $this->assertSame(39500.0, $rows['Laba kotor setelah bahan terbuang']);
        $this->assertSame(6400.0, $rows['Pajak terkumpul (titipan, bukan pendapatan)']);
    }

    public function test_hpp_in_report_is_the_snapshot_not_todays_cost(): void
    {
        $this->seedSales();
        $this->ing['beans']->update(['cost_per_unit' => 1000]); // beans now 4× more expensive

        $report = app(ReportService::class)->grossProfit($this->period());

        $this->assertSame(22500.0, $report['summary'][1]['value']);
    }

    public function test_ingredient_report_reconciles_with_stock(): void
    {
        $this->seedSales();
        $report = app(ReportService::class)->ingredients($this->period());
        $milk = collect($report['sections'][0]['rows'])->firstWhere(0, 'Susu');

        // opening: factory stock has no ledger rows → opening = −120 (31 Okt sale)
        // in period: used 3 × 120 = 360, wasted 100
        [, $unit, $open, $in, $used, $waste, $adjust, $close] = $milk;
        $this->assertSame('ml', $unit);
        $this->assertSame(360.0, $used);
        $this->assertSame(100.0, $waste);
        $this->assertSame(0.0, $in);
        $this->assertSame($open + $in - $used - $waste + $adjust, $close);
        $this->assertSame(2000.0, $report['summary'][1]['value']);
    }

    public function test_ingredient_report_matches_live_stock_when_ledger_is_complete(): void
    {
        $this->actingAs($this->owner());
        $inventory = app(InventoryService::class);
        $gula = $inventory->createIngredient(['name' => 'Gula Pasir', 'unit' => 'gram', 'min_stock' => 0, 'cost_per_unit' => 15], 1000);
        $inventory->recordPurchase(['purchased_at' => now()->toDateString()], [['ingredient_id' => $gula->id, 'quantity' => 500, 'subtotal' => 7500]]);
        $inventory->stockOut($gula, StockMutationType::Spilled, 50, 'Tumpah');
        $inventory->adjustTo($gula, 1400, 'Opname');

        $period = new ReportFilters(now()->startOfMonth(), now());
        $row = collect(app(ReportService::class)->ingredients($period)['sections'][0]['rows'])->firstWhere(0, 'Gula Pasir');

        $this->assertSame([0.0, 1500.0, 0.0, 50.0, -50.0, 1400.0], array_slice($row, 2, 6));
        $this->assertSame(1400.0, (float) $gula->fresh()->stock);
    }

    public function test_filters_by_cashier_and_method(): void
    {
        $this->seedSales();
        $otherCashierOrder = Order::create([
            'user_id' => $this->owner()->id, 'status' => 'paid', 'order_type' => 'take_away',
            'subtotal' => 10000, 'total' => 10000, 'grand_total' => 10000, 'paid_at' => '2026-11-02 10:00',
        ]);
        Payment::create(['order_id' => $otherCashierOrder->id, 'method' => 'qris', 'status' => 'paid', 'amount' => 10000]);

        $service = app(ReportService::class);
        $all = new ReportFilters(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $mine = new ReportFilters(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), $this->cashier->id);
        $qris = new ReportFilters(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), null, 'qris');

        $this->assertSame(74000.0, $service->sales($all)['summary'][0]['value']);
        $this->assertSame(64000.0, $service->sales($mine)['summary'][0]['value']);
        $this->assertSame(10000.0, $service->sales($qris)['summary'][0]['value']);
    }

    public function test_shift_report_lists_variance(): void
    {
        $this->seedSales();
        app(ShiftService::class)->close($this->shift, 100000, 'Uang kurang');
        $this->shift->update(['opened_at' => '2026-11-01 08:00']);

        $report = app(ReportService::class)->shiftReport($this->period());
        $row = $report['sections'][0]['rows'][0];

        // opening 200.000 + cash sales (22.000 + 44.000 + 20.000) = 286.000 expected
        $this->assertSame($this->cashier->name, $row[0]);
        $this->assertSame(86000, $row[4]);
        $this->assertSame(286000.0, $row[8]);
        $this->assertSame(-186000.0, $row[10]);
        $this->assertSame(1, $report['summary'][1]['value']);
    }

    public function test_report_pages_render_for_owner_only(): void
    {
        $this->seedSales();
        $owner = $this->owner();

        foreach (array_keys(ReportService::TYPES) as $type) {
            $this->actingAs($owner)->get("/owner/laporan/{$type}?from=2026-11-01&to=2026-11-30")->assertOk();
            $this->actingAs($this->cashier)->get("/owner/laporan/{$type}")->assertForbidden();
        }

        $this->actingAs($owner)->get('/owner/laporan/penjualan?from=2026-11-01&to=2026-11-30')
            ->assertSee('Rp64.000')->assertSee('Kopi Susu Aren');
        $this->actingAs($owner)->get('/owner/laporan/tidak-ada')->assertNotFound();
        $this->actingAs($owner)->get('/owner/laporan')->assertRedirect('/owner/laporan/penjualan');
    }

    public function test_pdf_export(): void
    {
        $this->seedSales();

        $response = $this->actingAs($this->owner())->get('/owner/laporan/laba-kotor/pdf?from=2026-11-01&to=2026-11-30');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('laporan-laba-kotor-20261101-20261130.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_excel_export(): void
    {
        Excel::fake();
        $this->seedSales();

        $this->actingAs($this->owner())->get('/owner/laporan/penjualan/excel?from=2026-11-01&to=2026-11-30')->assertOk();

        Excel::assertDownloaded('laporan-penjualan-20261101-20261130.xlsx', function (ReportExport $export) {
            $sheets = $export->sheets();
            $daily = $sheets[1]->array();

            return count($sheets) === 4
                && $sheets[1]->headings() === ['Tanggal', 'Transaksi', 'Item', 'Diskon', 'Omzet']
                && $daily[0] === ['2026-11-01', 1, 2, 0.0, 44000.0]
                && end($daily) === ['Total', 2, 3, 2000.0, 64000.0];
        });
    }

    public function test_real_excel_file_is_a_valid_workbook(): void
    {
        $this->seedSales();

        $response = $this->actingAs($this->owner())->get('/owner/laporan/bahan-baku/excel?from=2026-11-01&to=2026-11-30');

        $response->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $book = IOFactory::load($path);
        $this->assertSame(['Ringkasan', 'Mutasi per bahan'], $book->getSheetNames());
        $this->assertSame('Bahan', $book->getSheet(1)->getCell('A1')->getValue());
    }

    public function test_dashboard_shows_real_figures(): void
    {
        $this->seedSales();
        Carbon::setTestNow('2026-11-02 18:00');

        $this->actingAs($this->owner())->get('/owner')
            ->assertOk()
            ->assertSee('Rp20.000')      // omzet hari ini (2 Nov)
            ->assertSee('Rp64.000')      // omzet bulan ini
            ->assertSee('Kopi Susu Aren')
            ->assertViewHas('chart', fn ($chart) => count($chart['labels']) === 14 && end($chart['revenue']) === 20000.0);
    }
}
