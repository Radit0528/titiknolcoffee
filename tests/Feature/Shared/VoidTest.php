<?php

namespace Tests\Feature\Shared;

use App\Enums\OrderStatus;
use App\Enums\StockMutationType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderVoid;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ReportService;
use App\Services\ShiftService;
use App\Support\ReportFilters;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Cashier\PosTestCase;

class VoidTest extends PosTestCase
{
    private User $boss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boss = User::factory()->owner()->create();
        $this->boss->forceFill(['pin' => '482915'])->save();
    }

    private function paidOrder(array $overrides = []): Order
    {
        $this->checkout($this->cart(2, $overrides))->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    private function void(Order $order, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->cashier)
            ->from("/transaksi/{$order->id}")
            ->post("/transaksi/{$order->id}/void", $data + ['reason' => 'Salah input menu', 'pin' => '482915']);
    }

    public function test_void_restores_stock_and_excludes_order_from_sales(): void
    {
        $order = $this->paidOrder();
        $this->assertSame(1000 - 36.0, (float) $this->ing['beans']->fresh()->stock);

        $this->void($order)->assertRedirect("/transaksi/{$order->id}")->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Void, $order->status);
        $this->assertSame(1000.0, (float) $this->ing['beans']->fresh()->stock);
        $this->assertSame(1000.0, (float) $this->ing['milk']->fresh()->stock);
        $this->assertSame(3, $order->stockMutations()->where('type', StockMutationType::VoidReturn)->count());

        $void = OrderVoid::firstOrFail();
        $this->assertSame($this->cashier->id, $void->requested_by);
        $this->assertSame($this->boss->id, $void->approved_by);
        $this->assertSame(44000.0, (float) $void->amount);
        $this->assertTrue(AuditLog::where('action', 'order.voided')->where('auditable_id', $order->id)->exists());

        $summary = app(ShiftService::class)->summary($this->shift);
        $this->assertSame(0, $summary['cash_sales']);
        $this->assertSame(1, $summary['void_count']);
        $this->assertSame(44000, $summary['void_total']);

        $period = new ReportFilters(now()->startOfDay(), now()->startOfDay());
        $this->assertSame(0.0, app(ReportService::class)->sales($period)['summary'][0]['value']);
    }

    public function test_ingredient_report_nets_void_against_usage(): void
    {
        $this->paidOrder();
        $this->void($this->paidOrder());

        $period = new ReportFilters(now()->startOfDay(), now()->startOfDay());
        $milk = collect(app(ReportService::class)->ingredients($period)['sections'][0]['rows'])->firstWhere(0, 'Susu');

        $this->assertSame(240.0, $milk[4], 'only the non-voided 2 cups count as used');
    }

    public function test_wrong_pin_is_rejected_and_rate_limited(): void
    {
        $order = $this->paidOrder();
        RateLimiter::clear('owner-pin:'.$this->cashier->id);

        for ($i = 0; $i < 5; $i++) {
            $this->void($order, ['pin' => '000001'])->assertSessionHasErrors('pin', null, 'void');
        }

        // Correct PIN is refused while locked out.
        $this->void($order)->assertSessionHasErrorsIn('void', ['pin' => 'Terlalu banyak PIN salah. Coba lagi dalam 5 menit.']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(5, AuditLog::where('action', 'owner.pin_failed')->count());
    }

    public function test_reason_and_owner_pin_are_required(): void
    {
        $order = $this->paidOrder();

        $this->void($order, ['reason' => '', 'pin' => '12'])->assertSessionHasErrorsIn('void', ['reason', 'pin']);

        User::query()->update(['pin' => null]);
        $this->void($order)->assertSessionHasErrorsIn('void', ['pin' => 'Owner belum mengatur PIN. Atur dulu di menu Akun & PIN.']);

        $this->boss->forceFill(['pin' => '482915', 'is_active' => false])->save();
        $this->void($order)->assertSessionHasErrors('pin', null, 'void');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_cannot_void_twice_or_after_shift_closed(): void
    {
        $order = $this->paidOrder();
        $this->void($order)->assertSessionHasNoErrors();
        $this->void($order)->assertSessionHasErrorsIn('void', ['reason' => 'Transaksi ini sudah dibatalkan.']);
        $this->assertSame(1000.0, (float) $this->ing['beans']->fresh()->stock, 'stock returned only once');

        $other = $this->paidOrder();
        $this->actingAs($this->cashier);
        app(ShiftService::class)->close($this->shift, 200000 + 44000, null);

        $this->void($other, [], $this->boss)->assertSessionHasErrorsIn('void', ['reason' => 'Shift transaksi ini sudah ditutup, jadi tidak bisa dibatalkan lagi.']);
        $this->assertSame(OrderStatus::Paid, $other->fresh()->status);
    }

    public function test_cashier_cannot_void_someone_elses_order(): void
    {
        $order = $this->paidOrder();
        $intruder = User::factory()->cashier()->create();

        $this->void($order, [], $intruder)->assertForbidden();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_owner_can_void_with_pin(): void
    {
        $order = $this->paidOrder();

        $this->void($order, [], $this->boss)->assertSessionHasNoErrors();

        $this->assertSame($this->boss->id, OrderVoid::first()->requested_by);
    }

    public function test_void_releases_voucher_quota(): void
    {
        $voucher = Voucher::factory()->fixed(2000)->create(['code' => 'HEMAT', 'quota' => 1]);
        $order = $this->paidOrder(['voucher_code' => 'HEMAT']);
        $this->assertSame(1, $voucher->fresh()->used_count);

        $this->void($order)->assertSessionHasNoErrors();

        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertSame(0, $voucher->usages()->count());
        $this->paidOrder(['voucher_code' => 'HEMAT']); // quota is usable again
    }

    public function test_order_page_and_receipt_show_void(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}")->assertOk()->assertSee('Batalkan (void)');

        $this->void($order);

        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}")
            ->assertOk()
            ->assertDontSee('Batalkan (void)')
            ->assertDontSee('Pembayaran berhasil')
            ->assertSee('DIBATALKAN')
            ->assertSee('Salah input menu');
        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}/cetak")->assertSee('DIBATALKAN');
    }
}
