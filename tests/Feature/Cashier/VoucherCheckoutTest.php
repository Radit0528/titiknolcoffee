<?php

namespace Tests\Feature\Cashier;

use App\Models\Order;
use App\Models\Voucher;

class VoucherCheckoutTest extends PosTestCase
{
    public function test_percent_voucher_is_applied_and_counted(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'NGOPI10', 'value' => 10, 'max_discount' => 3000]);

        // 2 × 22.000 = 44.000; 10% = 4.400 capped at 3.000
        $this->checkout($this->cart(2, ['voucher_code' => 'ngopi10']))->assertOk();
        $order = Order::sole();

        $this->assertSame('3000.00', $order->discount);
        $this->assertSame('41000.00', $order->grand_total);
        $this->assertSame('NGOPI10', $order->voucher_code);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('voucher_usages', ['voucher_id' => $voucher->id, 'order_id' => $order->id, 'discount' => 3000]);
    }

    public function test_fixed_voucher_never_exceeds_subtotal(): void
    {
        Voucher::factory()->fixed(50000)->create(['code' => 'GRATIS']);

        $this->checkout($this->cart(1, ['voucher_code' => 'GRATIS', 'cash_received' => 0]))->assertOk();

        $this->assertSame('22000.00', Order::sole()->discount);
        $this->assertSame('0.00', Order::sole()->grand_total);
    }

    public function test_expired_voucher_is_rejected(): void
    {
        Voucher::factory()->create(['code' => 'LAMA', 'ends_at' => now()->subDay()]);

        $this->checkout($this->cart(1, ['voucher_code' => 'LAMA']))
            ->assertStatus(422)
            ->assertJson(['field' => 'voucher_code', 'message' => 'Voucher sudah kedaluwarsa.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_not_yet_started_inactive_unknown_and_minimum_purchase(): void
    {
        Voucher::factory()->create(['code' => 'BESOK', 'starts_at' => now()->addDay()]);
        Voucher::factory()->create(['code' => 'MATI', 'is_active' => false]);
        Voucher::factory()->create(['code' => 'MIN50', 'min_purchase' => 50000]);

        $this->checkout($this->cart(1, ['voucher_code' => 'BESOK']))->assertStatus(422);
        $this->checkout($this->cart(1, ['voucher_code' => 'MATI']))->assertStatus(422)->assertJsonPath('message', 'Voucher sedang tidak aktif.');
        $this->checkout($this->cart(1, ['voucher_code' => 'NGAWUR']))->assertStatus(422)->assertJsonPath('message', 'Kode voucher tidak ditemukan.');
        $this->checkout($this->cart(2, ['voucher_code' => 'MIN50']))->assertStatus(422)->assertJsonPath('message', 'Minimum pembelian untuk voucher ini Rp50.000.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quota_is_enforced(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'SEKALI', 'quota' => 1]);

        $this->checkout($this->cart(1, ['voucher_code' => 'SEKALI']))->assertOk();
        $this->checkout($this->cart(1, ['voucher_code' => 'SEKALI']))->assertStatus(422)->assertJsonPath('message', 'Kuota voucher sudah habis.');

        $this->assertSame(1, $voucher->fresh()->used_count);
    }

    public function test_failed_checkout_does_not_use_voucher_quota(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'HEMAT', 'quota' => 5]);
        $this->ing['milk']->forceFill(['stock' => 0])->save();

        $this->checkout($this->cart(1, ['voucher_code' => 'HEMAT']))->assertStatus(422);

        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertDatabaseCount('voucher_usages', 0);
    }

    public function test_quote_endpoint_returns_server_totals(): void
    {
        Voucher::factory()->fixed(5000)->create(['code' => 'LIMARIBU']);

        $this->actingAs($this->cashier)->postJson('/kasir/quote', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2]],
            'order_type' => 'take_away',
            'voucher_code' => 'LIMARIBU',
        ])->assertOk()->assertJson(['subtotal' => 44000, 'discount' => 5000, 'total' => 39000, 'voucher' => ['code' => 'LIMARIBU']]);

        $this->assertSame(0, Voucher::sole()->used_count, 'a quote never redeems');
    }
}
