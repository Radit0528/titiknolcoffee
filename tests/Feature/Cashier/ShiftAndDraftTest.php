<?php

namespace Tests\Feature\Cashier;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Database\UniqueConstraintViolationException;

class ShiftAndDraftTest extends PosTestCase
{
    public function test_cashier_opens_a_shift(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get('/kasir')->assertRedirect(route('cashier.shift.create'));
        $this->actingAs($cashier)->get('/kasir/shift/buka')->assertOk()->assertSee('Modal awal');

        $this->actingAs($cashier)->post('/kasir/shift', ['opening_cash' => 250000])->assertRedirect(route('cashier.pos'));

        $shift = Shift::where('user_id', $cashier->id)->sole();
        $this->assertSame('250000.00', $shift->opening_cash);
        $this->assertTrue($shift->isOpen());
        $this->actingAs($cashier)->get('/kasir')->assertOk();
    }

    public function test_cashier_cannot_have_two_open_shifts(): void
    {
        $this->actingAs($this->cashier)->post('/kasir/shift', ['opening_cash' => 100000])
            ->assertRedirect(route('cashier.pos'))
            ->assertSessionHas('error');

        $this->assertSame(1, Shift::where('user_id', $this->cashier->id)->count());
    }

    public function test_database_blocks_a_second_open_shift_even_if_the_check_is_bypassed(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        Shift::create(['user_id' => $this->cashier->id, 'open_user_id' => $this->cashier->id, 'opening_cash' => 0, 'opened_at' => now()]);
    }

    public function test_opening_cash_is_validated(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->post('/kasir/shift', ['opening_cash' => -1])->assertSessionHasErrors('opening_cash');
        $this->assertNull(app(ShiftService::class)->current($cashier));
    }

    public function test_draft_is_saved_without_touching_stock(): void
    {
        $this->actingAs($this->cashier)->postJson('/kasir/draft', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 3, 'note' => 'es dikit']],
            'order_type' => 'take_away',
            'customer_name' => 'Meja 4',
        ])->assertOk()->assertJson(['redirect' => route('cashier.pos')]);

        $draft = Order::sole();
        $this->assertSame(OrderStatus::Draft, $draft->status);
        $this->assertNull($draft->code);
        $this->assertSame(3, $draft->items()->sole()->quantity);
        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertSame('1000.000', $this->ing['milk']->fresh()->stock);

        $this->actingAs($this->cashier)->get('/kasir/draft')->assertOk()->assertSee('Meja 4');
    }

    public function test_draft_resumes_into_pos_and_is_replaced_by_the_paid_order(): void
    {
        $this->actingAs($this->cashier)->postJson('/kasir/draft', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]],
            'order_type' => 'dine_in',
        ]);
        $draft = Order::sole();

        $this->actingAs($this->cashier)->get("/kasir?draft={$draft->id}")
            ->assertOk()
            ->assertViewHas('draft', fn ($d) => $d['id'] === $draft->id && $d['items'][0]['quantity'] === 1);

        $this->checkout($this->cart(1, ['draft_id' => $draft->id]))->assertOk();

        $this->assertModelMissing($draft);
        $this->assertSame(OrderStatus::Paid, Order::sole()->status);
    }

    public function test_updating_a_draft_replaces_its_items(): void
    {
        $this->actingAs($this->cashier)->postJson('/kasir/draft', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]], 'order_type' => 'dine_in']);
        $draft = Order::sole();

        $this->actingAs($this->cashier)->postJson('/kasir/draft', [
            'draft_id' => $draft->id, 'items' => [['product_id' => $this->kopi->id, 'quantity' => 4]], 'order_type' => 'dine_in',
        ])->assertOk();

        $this->assertSame(1, Order::count());
        $this->assertSame(4, $draft->fresh()->items()->sole()->quantity);
    }

    public function test_cashier_cannot_touch_another_cashiers_draft(): void
    {
        $this->actingAs($this->cashier)->postJson('/kasir/draft', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]], 'order_type' => 'dine_in']);
        $draft = Order::sole();

        $other = User::factory()->cashier()->create();
        Shift::factory()->for($other)->create();

        $this->actingAs($other)->delete("/kasir/draft/{$draft->id}")->assertNotFound();
        $this->actingAs($other)->get("/kasir?draft={$draft->id}")->assertViewHas('draft', null);
        $this->assertModelExists($draft);

        $this->actingAs($this->cashier)->delete("/kasir/draft/{$draft->id}")->assertRedirect(route('cashier.drafts.index'));
        $this->assertModelMissing($draft);
    }
}
