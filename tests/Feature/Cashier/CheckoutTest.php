<?php

namespace Tests\Feature\Cashier;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMutationType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMutation;
use App\Services\RecipeService;
use App\Services\SettingService;

class CheckoutTest extends PosTestCase
{
    public function test_cash_checkout_creates_paid_order_with_correct_change(): void
    {
        $response = $this->checkout($this->cart(2, ['cash_received' => 50000]));

        $order = Order::sole();
        $response->assertOk()->assertJson(['order' => $order->code, 'redirect' => route('orders.show', $order)]);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertMatchesRegularExpression('/^TN-\d{6}-\d{5}$/', $order->code);
        $this->assertSame($this->shift->id, $order->shift_id);
        $this->assertSame('44000.00', $order->subtotal);
        $this->assertSame('44000.00', $order->grand_total);
        $this->assertSame('Rani', $order->customer_name);

        $payment = $order->payment;
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertSame('50000.00', $payment->cash_received);
        $this->assertSame('6000.00', $payment->change);
    }

    public function test_stock_is_deducted_by_recipe_and_logged(): void
    {
        $this->checkout($this->cart(2))->assertOk();
        $order = Order::sole();

        // 2 cups: beans 36 g, milk 240 ml, aren 40 ml
        $this->assertSame('964.000', $this->ing['beans']->fresh()->stock);
        $this->assertSame('760.000', $this->ing['milk']->fresh()->stock);
        $this->assertSame('960.000', $this->ing['aren']->fresh()->stock);

        $mutations = StockMutation::where('type', StockMutationType::Sale)->get();
        $this->assertCount(3, $mutations);
        $this->assertTrue($mutations->every(fn ($m) => $m->reference_id === $order->id && $m->reference_type === $order->getMorphClass()));
        $this->assertSame('Penjualan '.$order->code, $mutations->first()->notes);
    }

    public function test_price_and_hpp_are_snapshotted(): void
    {
        $this->checkout($this->cart(2))->assertOk();
        $item = Order::sole()->items()->sole();

        $this->assertSame('Kopi Susu Aren', $item->product_name);
        $this->assertSame('22000.00', $item->unit_price);
        $this->assertSame('7500.00', $item->unit_hpp);
        $this->assertSame('less sugar', $item->note);
        $this->assertSame('15000.00', Order::sole()->hpp_total);

        // Later menu changes do not touch the old order.
        $this->kopi->update(['price' => 30000, 'name' => 'Kopi Aren Baru']);
        $this->assertSame('22000.00', $item->fresh()->unit_price);
        $this->assertSame('Kopi Susu Aren', $item->fresh()->product_name);
    }

    public function test_prices_from_the_browser_are_ignored(): void
    {
        $payload = $this->cart(1, ['cash_received' => 22000]);
        $payload['items'][0]['price'] = 1;
        $payload['subtotal'] = 1;
        $payload['total'] = 1;

        $this->checkout($payload)->assertOk();

        $this->assertSame('22000.00', Order::sole()->grand_total);
    }

    public function test_insufficient_cash_is_rejected_without_side_effects(): void
    {
        $this->checkout($this->cart(2, ['cash_received' => 40000]))
            ->assertStatus(422)
            ->assertJson(['field' => 'cash_received']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('1000.000', $this->ing['milk']->fresh()->stock);
    }

    public function test_checkout_is_rejected_when_stock_is_short(): void
    {
        $this->ing['milk']->forceFill(['stock' => 200])->save(); // enough for 1 cup, not 2

        $this->checkout($this->cart(2))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Stok Susu tidak cukup') && str_contains($m, 'Kopi Susu Aren'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertSame('1000.000', $this->ing['beans']->fresh()->stock, 'nothing was deducted');
    }

    public function test_shared_ingredients_are_summed_across_lines(): void
    {
        $latte = Product::factory()->for($this->kopi->category)->create(['name' => 'Latte', 'price' => 25000]);
        app(RecipeService::class)->sync($latte, [['ingredient_id' => $this->ing['milk']->id, 'quantity' => 600]]);

        // 1 kopi (120 ml) + 1 latte (600 ml) = 720 ml ≤ 1000: ok
        $this->checkout($this->cart(1, [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1], ['product_id' => $latte->id, 'quantity' => 1]],
        ]))->assertOk();
        $this->assertSame('280.000', $this->ing['milk']->fresh()->stock);

        // Another of each needs 720 ml but only 280 ml is left.
        $this->checkout($this->cart(1, [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1], ['product_id' => $latte->id, 'quantity' => 1]],
        ]))->assertStatus(422);
    }

    public function test_unavailable_product_is_rejected(): void
    {
        $this->kopi->update(['is_available' => false]);

        $this->checkout($this->cart())->assertStatus(422)->assertJsonPath('message', 'Kopi Susu Aren sedang habis.');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_deleted_or_unknown_product_is_rejected(): void
    {
        $this->checkout($this->cart(1, ['items' => [['product_id' => 999999, 'quantity' => 1]]]))->assertStatus(422);

        $this->kopi->delete();
        $this->checkout($this->cart())->assertStatus(422);
    }

    public function test_same_client_token_never_creates_two_orders(): void
    {
        $payload = $this->cart(1);

        $first = $this->checkout($payload)->assertOk()->json('order');
        $second = $this->checkout($payload)->assertOk()->json('order');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('982.000', $this->ing['beans']->fresh()->stock, 'stock taken once');
    }

    public function test_tax_service_charge_and_cash_rounding(): void
    {
        app(SettingService::class)->update(['tax_percent' => 10, 'service_charge_percent' => 5, 'cash_rounding' => 500]);

        // subtotal 22.000 → service 1.100 → tax (23.100 × 10%) 2.310 → total 25.410 → cash 25.500
        $this->checkout($this->cart(1, ['cash_received' => 30000]))->assertOk();
        $order = Order::sole();

        $this->assertSame('1100.00', $order->service_charge);
        $this->assertSame('2310.00', $order->tax);
        $this->assertSame('25410.00', $order->total);
        $this->assertSame('90.00', $order->rounding);
        $this->assertSame('25500.00', $order->grand_total);
        $this->assertSame('4500.00', $order->payment->change);
        $this->assertSame('10.00', $order->tax_rate);
    }

    public function test_cashier_without_open_shift_cannot_sell(): void
    {
        $this->shift->update(['closed_at' => now(), 'open_user_id' => null]);

        $this->checkout($this->cart())->assertStatus(409);
        $this->actingAs($this->cashier)->get('/kasir')->assertRedirect(route('cashier.shift.create'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_owner_cannot_use_checkout(): void
    {
        $this->actingAs($this->owner())->postJson('/kasir/checkout', $this->cart())->assertForbidden();
    }

    public function test_empty_cart_and_bad_quantities_are_rejected(): void
    {
        $this->checkout($this->cart(1, ['items' => []]))->assertStatus(422)->assertJsonValidationErrors('items');
        $this->checkout($this->cart(0))->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
        $this->checkout($this->cart(1, ['client_token' => 'not-a-uuid']))->assertStatus(422)->assertJsonValidationErrors('client_token');
    }

    public function test_pos_page_shows_servings_left(): void
    {
        $this->ing['beans']->forceFill(['stock' => 54])->save(); // 3 cups

        $this->actingAs($this->cashier)->get('/kasir')
            ->assertOk()
            ->assertViewHas('catalog', fn ($catalog) => $catalog->firstWhere('id', $this->kopi->id)['servings'] === 3);
    }
}
