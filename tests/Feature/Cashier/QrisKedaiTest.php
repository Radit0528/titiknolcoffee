<?php

namespace Tests\Feature\Cashier;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use App\Services\QrisService;
use App\Services\SettingService;
use App\Services\ShiftService;
use Illuminate\Support\Str;
use Tests\Unit\QrisServiceTest;

class QrisKedaiTest extends PosTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->update(['qris_static_payload' => QrisServiceTest::staticPayload()]);
    }

    private function qrisKedai(array $overrides = [])
    {
        $cart = $this->cart(2, $overrides);
        unset($cart['cash_received']);

        return $this->checkout(['method' => 'qris_kedai'] + $cart);
    }

    public function test_creates_pending_order_with_amount_in_qr_and_reserves_stock(): void
    {
        $response = $this->qrisKedai()->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('amount', 44000);

        $order = Order::with('payment')->latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::QrisKedai, $order->payment->method);
        $this->assertSame(1000 - 36.0, (float) $this->ing['beans']->fresh()->stock, 'stock reserved while waiting');

        $fields = app(QrisService::class)->parse($response->json('qr_payload'));
        $this->assertSame('12', $fields['01']);
        $this->assertSame('44000', $fields['54']);
        $this->assertSame('KEDAI CONTOH', $fields['59']);
    }

    public function test_cashier_confirms_payment(): void
    {
        $confirmUrl = $this->qrisKedai()->json('confirm_url');
        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)->postJson($confirmUrl)->assertOk()->assertJsonPath('redirect', route('orders.show', $order));

        $order->refresh()->load('payment');
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment->status);
        $this->assertSame($this->cashier->id, $order->payment->confirmed_by);
        $this->assertTrue(AuditLog::where('action', 'payment.qris_kedai_confirmed')->exists());

        // Counted as non-cash: not in the drawer.
        $summary = app(ShiftService::class)->summary($this->shift);
        $this->assertSame(0, $summary['cash_sales']);
        $this->assertSame(44000, $summary['qris_sales']);
    }

    public function test_cancel_returns_stock_and_voucher(): void
    {
        $voucher = Voucher::factory()->fixed(2000)->create(['code' => 'HEMAT', 'quota' => 1]);
        $cancelUrl = $this->qrisKedai(['voucher_code' => 'HEMAT'])->json('cancel_url');
        $this->assertSame(1, $voucher->fresh()->used_count);

        $this->actingAs($this->cashier)->postJson($cancelUrl)->assertOk();

        $this->assertSame(OrderStatus::Expired, Order::latest('id')->first()->status);
        $this->assertSame(1000.0, (float) $this->ing['beans']->fresh()->stock);
        $this->assertSame(0, $voucher->fresh()->used_count);
    }

    public function test_doku_orders_cannot_be_confirmed_by_hand(): void
    {
        $order = Order::create(['user_id' => $this->cashier->id, 'shift_id' => $this->shift->id, 'status' => 'pending', 'order_type' => 'take_away',
            'subtotal' => 10000, 'total' => 10000, 'grand_total' => 10000, 'client_token' => (string) Str::uuid()]);
        $order->payment()->create(['method' => PaymentMethod::Qris, 'status' => PaymentStatus::Pending, 'amount' => 10000]);

        $this->actingAs($this->cashier)->postJson(route('cashier.pos.payment.confirm', $order))
            ->assertStatus(422)->assertJsonPath('message', 'Pembayaran ini tidak bisa dikonfirmasi manual.');
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_other_cashier_cannot_confirm(): void
    {
        $confirmUrl = $this->qrisKedai()->json('confirm_url');

        $other = User::factory()->cashier()->create();
        Shift::factory()->for($other)->create();

        $this->actingAs($other)->postJson($confirmUrl)->assertForbidden();
    }

    public function test_unavailable_when_owner_has_not_set_qris(): void
    {
        app(SettingService::class)->update(['qris_static_payload' => '']);

        $this->qrisKedai()->assertStatus(422)->assertJsonPath('message', 'QRIS Kedai belum diatur oleh Owner.');
        $this->actingAs($this->cashier)->get('/kasir')->assertOk()->assertViewHas('config', fn ($c) => $c['qrisKedai'] === false);
    }

    public function test_double_submit_creates_one_order(): void
    {
        $cart = $this->cart(1);
        unset($cart['cash_received']);
        $body = ['method' => 'qris_kedai'] + $cart;

        $this->checkout($body)->assertOk();
        $this->checkout($body)->assertOk();

        $this->assertSame(1, Order::where('status', 'pending')->count());
    }
}
