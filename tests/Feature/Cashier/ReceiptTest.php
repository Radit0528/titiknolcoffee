<?php

namespace Tests\Feature\Cashier;

use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\SettingService;

class ReceiptTest extends PosTestCase
{
    private function paidOrder(): Order
    {
        app(SettingService::class)->update([
            'store_name' => 'Kopi Titik Nol', 'store_address' => 'Jl. Merdeka 1, Purwokerto', 'receipt_footer' => 'Sampai jumpa!',
        ]);
        $this->checkout($this->cart(2, ['cash_received' => 50000]))->assertOk();

        return Order::sole();
    }

    public function test_receipt_shows_everything_the_spec_requires(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}")
            ->assertOk()
            ->assertSee('Pembayaran berhasil')
            ->assertSee('Kopi Titik Nol')
            ->assertSee('Jl. Merdeka 1, Purwokerto')
            ->assertSee($order->code)
            ->assertSee($this->cashier->name)
            ->assertSee('Rani')
            ->assertSee('Kopi Susu Aren')
            ->assertSee('less sugar')
            ->assertSee('Rp44.000')
            ->assertSee('Rp50.000')
            ->assertSee('Rp6.000')
            ->assertSee('Sampai jumpa!')
            ->assertSee('Transaksi baru');
    }

    public function test_print_page_supports_58_and_80_mm(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}/cetak?width=58")->assertOk()->assertSee('receipt-58');
        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}/cetak?width=80")->assertOk()->assertSee('receipt-80');
        $this->actingAs($this->cashier)->get("/transaksi/{$order->id}/cetak?width=999")->assertOk()->assertSee('receipt-58');
    }

    public function test_cashier_sees_only_own_transactions_owner_sees_all(): void
    {
        $order = $this->paidOrder();
        $other = User::factory()->cashier()->create();
        Shift::factory()->for($other)->create();

        $this->actingAs($other)->get("/transaksi/{$order->id}")->assertForbidden();
        $this->actingAs($other)->get("/transaksi/{$order->id}/cetak")->assertForbidden();
        $this->actingAs($other)->get('/transaksi')->assertOk()->assertDontSee($order->code);

        $this->actingAs($this->cashier)->get('/transaksi')->assertOk()->assertSee($order->code);
        $this->actingAs($this->owner())->get('/transaksi')->assertOk()->assertSee($order->code);
        $this->actingAs($this->owner())->get("/transaksi/{$order->id}")->assertOk()->assertDontSee('Pembayaran berhasil');
    }

    public function test_drafts_have_no_receipt(): void
    {
        $this->actingAs($this->cashier)->postJson('/kasir/draft', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]], 'order_type' => 'take_away']);

        $this->actingAs($this->cashier)->get('/transaksi/'.Order::sole()->id)->assertNotFound();
    }
}
