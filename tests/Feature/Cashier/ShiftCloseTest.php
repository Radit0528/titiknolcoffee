<?php

namespace Tests\Feature\Cashier;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\User;
use App\Services\SettingService;
use App\Services\ShiftService;

class ShiftCloseTest extends PosTestCase
{
    /** Shift opens with Rp200.000 (ShiftFactory). One cash sale of 2 × Rp22.000. */
    private function sellCash(int $quantity = 2): void
    {
        $this->checkout($this->cart($quantity, ['cash_received' => 100000]))->assertOk();
    }

    /** A paid QRIS order in this shift, created directly (the payment gateway is added at the final stage). */
    private function paidQris(int $amount): void
    {
        $order = Order::create([
            'shift_id' => $this->shift->id, 'user_id' => $this->cashier->id, 'status' => OrderStatus::Paid,
            'order_type' => 'take_away', 'subtotal' => $amount, 'total' => $amount, 'grand_total' => $amount, 'paid_at' => now(),
        ]);
        Payment::create(['order_id' => $order->id, 'method' => 'qris', 'status' => 'paid', 'amount' => $amount, 'paid_at' => now()]);
    }

    private function cash(string $type, int $amount, string $reason = 'Uji')
    {
        return $this->actingAs($this->cashier)->post('/kasir/shift/kas', compact('type', 'amount', 'reason'));
    }

    public function test_cash_in_and_out_are_recorded(): void
    {
        $this->cash('in', 50000, 'Tambah uang kembalian')->assertRedirect(route('cashier.shift.current'));
        $this->cash('out', 15000, 'Beli es batu')->assertRedirect(route('cashier.shift.current'));

        $this->assertSame(2, ShiftCashMovement::where('shift_id', $this->shift->id)->count());
        $this->assertDatabaseHas('shift_cash_movements', ['type' => 'out', 'amount' => 15000, 'reason' => 'Beli es batu', 'user_id' => $this->cashier->id]);

        $this->actingAs($this->cashier)->get('/kasir/shift')->assertOk()->assertSee('Beli es batu')->assertSee('Rp235.000');
    }

    public function test_cash_out_cannot_exceed_the_drawer(): void
    {
        $this->cash('out', 200001)->assertSessionHasErrors('amount');
        $this->cash('out', 200000)->assertSessionHasNoErrors();
        $this->cash('out', 1)->assertSessionHasErrors('amount');
    }

    public function test_cash_movement_validation(): void
    {
        $this->actingAs($this->cashier)->post('/kasir/shift/kas', ['type' => 'transfer', 'amount' => 0, 'reason' => ''])
            ->assertSessionHasErrors(['type', 'amount', 'reason']);
    }

    public function test_summary_follows_the_expected_cash_formula(): void
    {
        $this->sellCash(2);           // cash +44.000
        $this->paidQris(30000);       // QRIS, not in the drawer
        $this->cash('in', 50000);
        $this->cash('out', 15000);

        $summary = app(ShiftService::class)->summary($this->shift);

        // 200.000 + 44.000 + 50.000 − 15.000
        $this->assertSame(279000, $summary['expected_cash']);
        $this->assertSame(44000, $summary['cash_sales']);
        $this->assertSame(30000, $summary['qris_sales']);
        $this->assertSame(74000, $summary['total_sales']);
        $this->assertSame(2, $summary['order_count']);
        $this->assertSame(2, $summary['item_count']);
    }

    public function test_cash_rounding_is_part_of_the_drawer(): void
    {
        app(SettingService::class)->update(['cash_rounding' => 500, 'tax_percent' => 10]);
        // 22.000 + 10% = 24.200 → 24.000 paid in cash
        $this->checkout($this->cart(1, ['cash_received' => 24000]))->assertOk();

        $this->assertSame(224000, app(ShiftService::class)->summary($this->shift)['expected_cash']);
    }

    public function test_closing_with_exact_cash(): void
    {
        $this->sellCash(2);

        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 244000])
            ->assertRedirect(route('shifts.show', $this->shift));

        $shift = $this->shift->fresh();
        $this->assertFalse($shift->isOpen());
        $this->assertNull($shift->open_user_id);
        $this->assertSame('244000.00', $shift->expected_cash);
        $this->assertSame('244000.00', $shift->closing_cash);
        $this->assertSame('0.00', $shift->cash_variance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shift.closed', 'auditable_id' => $shift->id]);
    }

    public function test_variance_must_be_explained(): void
    {
        $this->sellCash(2);

        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 240000])
            ->assertSessionHasErrors(['closing_notes' => 'Uang fisik kurang Rp4.000. Tulis penjelasan selisih kas.']);
        $this->assertTrue($this->shift->fresh()->isOpen());

        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 240000, 'closing_notes' => 'Salah kembalian ke pelanggan'])
            ->assertRedirect();

        $this->assertSame('-4000.00', $this->shift->fresh()->cash_variance);
        $this->actingAs($this->cashier)->get("/shift/{$this->shift->id}")->assertOk()->assertSee('Uang kurang')->assertSee('Salah kembalian ke pelanggan');
    }

    public function test_cannot_close_while_qris_is_waiting(): void
    {
        $order = Order::create([
            'shift_id' => $this->shift->id, 'user_id' => $this->cashier->id, 'status' => OrderStatus::Pending,
            'order_type' => 'take_away', 'subtotal' => 22000, 'total' => 22000, 'grand_total' => 22000,
        ]);

        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 200000])
            ->assertSessionHasErrors(['closing_cash' => 'Masih ada 1 transaksi yang menunggu pembayaran. Selesaikan atau batalkan dulu.']);

        $this->assertTrue($this->shift->fresh()->isOpen());

        $order->update(['status' => OrderStatus::Expired]);
        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 200000])->assertSessionHasNoErrors();
    }

    public function test_after_closing_the_cashier_must_open_a_new_shift(): void
    {
        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 200000]);

        $this->actingAs($this->cashier)->get('/kasir')->assertRedirect(route('cashier.shift.create'));
        $this->checkout($this->cart())->assertStatus(409);
        $this->cash('in', 1000)->assertRedirect(route('cashier.shift.create'));

        $this->actingAs($this->cashier)->post('/kasir/shift', ['opening_cash' => 150000])->assertRedirect(route('cashier.pos'));
        $this->assertSame(2, Shift::where('user_id', $this->cashier->id)->count());
        $this->actingAs($this->cashier)->get('/kasir/shift/buka')->assertRedirect(route('cashier.pos'));
    }

    public function test_closed_shift_cannot_be_closed_or_used_again(): void
    {
        $service = app(ShiftService::class);
        $service->close($this->shift, 200000, null);

        $this->expectException(CheckoutException::class);
        $service->close($this->shift->fresh(), 200000, null);
    }

    public function test_shift_report_access(): void
    {
        $this->sellCash(1);
        $this->actingAs($this->cashier)->post('/kasir/shift/tutup', ['closing_cash' => 222000]);

        $other = User::factory()->cashier()->create();

        $this->actingAs($this->cashier)->get("/shift/{$this->shift->id}")->assertOk()->assertSee('Kas sesuai');
        $this->actingAs($this->cashier)->get("/shift/{$this->shift->id}/cetak")->assertOk()->assertSee('LAPORAN SHIFT')->assertSee('Rp222.000');
        $this->actingAs($other)->get("/shift/{$this->shift->id}")->assertForbidden();
        $this->actingAs($this->owner())->get("/shift/{$this->shift->id}")->assertOk();

        $this->actingAs($this->cashier)->get('/kasir/shift/buka')->assertOk()->assertSee('Shift sebelumnya');
    }

    public function test_owner_cannot_record_cash_or_close_cashier_shift(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/kasir/shift/kas', ['type' => 'in', 'amount' => 1000, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($owner)->post('/kasir/shift/tutup', ['closing_cash' => 0])->assertForbidden();
    }
}
