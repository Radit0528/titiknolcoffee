<?php

namespace Tests\Feature\Owner;

use App\Enums\StockMutationType;
use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\StockMutation;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_out_reduces_stock_and_logs_reason(): void
    {
        $owner = $this->owner();
        $milk = Ingredient::factory()->create(['name' => 'Susu', 'stock' => 1000, 'cost_per_unit' => 20]);

        $this->actingAs($owner)->post('/owner/stock/out', [
            'ingredient_id' => $milk->id, 'type' => 'expired', 'quantity' => 250, 'reason' => 'Lewat tanggal',
        ])->assertRedirect(route('owner.stock.index'));

        $this->assertSame('750.000', $milk->fresh()->stock);

        $mutation = StockMutation::sole();
        $this->assertSame(StockMutationType::Expired, $mutation->type);
        $this->assertSame('-250.000', $mutation->quantity);
        $this->assertSame('1000.000', $mutation->stock_before);
        $this->assertSame('750.000', $mutation->stock_after);
        $this->assertSame('20.0000', $mutation->cost_per_unit);
        $this->assertSame('Lewat tanggal', $mutation->notes);
        $this->assertSame($owner->id, $mutation->user_id);
    }

    public function test_stock_out_cannot_exceed_stock(): void
    {
        $milk = Ingredient::factory()->create(['stock' => 100]);

        $this->actingAs($this->owner())->post('/owner/stock/out', [
            'ingredient_id' => $milk->id, 'type' => 'spilled', 'quantity' => 100.001, 'reason' => 'Tumpah',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame('100.000', $milk->fresh()->stock);
        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_stock_out_type_must_be_a_waste_reason(): void
    {
        $milk = Ingredient::factory()->create();

        foreach (['purchase', 'adjustment', 'sale', 'initial', 'nonsense'] as $type) {
            $this->actingAs($this->owner())->post('/owner/stock/out', [
                'ingredient_id' => $milk->id, 'type' => $type, 'quantity' => 1, 'reason' => 'x',
            ])->assertSessionHasErrors('type');
        }

        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_stock_out_requires_reason(): void
    {
        $milk = Ingredient::factory()->create();

        $this->actingAs($this->owner())->post('/owner/stock/out', [
            'ingredient_id' => $milk->id, 'type' => 'damaged', 'quantity' => 1, 'reason' => '',
        ])->assertSessionHasErrors('reason');
    }

    public function test_adjustment_records_the_difference_either_way(): void
    {
        $beans = Ingredient::factory()->create(['stock' => 1000]);
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/stock/adjustment', [
            'ingredient_id' => $beans->id, 'counted_stock' => 940.5, 'reason' => 'Opname akhir bulan',
        ])->assertRedirect(route('owner.stock.index'));

        $this->assertSame('940.500', $beans->fresh()->stock);
        $this->assertSame('-59.500', StockMutation::latest('id')->first()->quantity);

        $this->actingAs($owner)->post('/owner/stock/adjustment', [
            'ingredient_id' => $beans->id, 'counted_stock' => 1000, 'reason' => 'Ternyata ada 1 bungkus di gudang',
        ]);

        $this->assertSame('1000.000', $beans->fresh()->stock);
        $this->assertSame('59.500', StockMutation::latest('id')->first()->quantity);
        $this->assertSame(StockMutationType::Adjustment, StockMutation::latest('id')->first()->type);
    }

    public function test_adjustment_with_no_difference_records_nothing(): void
    {
        $beans = Ingredient::factory()->create(['stock' => 1000]);

        $this->actingAs($this->owner())->post('/owner/stock/adjustment', [
            'ingredient_id' => $beans->id, 'counted_stock' => 1000, 'reason' => 'Cek',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_service_refuses_to_go_negative(): void
    {
        $ingredient = Ingredient::factory()->create(['stock' => 5]);

        $this->expectException(InsufficientStockException::class);

        app(InventoryService::class)->applyMutation($ingredient, StockMutationType::Sale, -6);
    }

    public function test_ledger_always_explains_current_stock(): void
    {
        $this->actingAs($this->owner());
        $service = app(InventoryService::class);
        $ingredient = $service->createIngredient(['name' => 'Gula', 'unit' => 'gram', 'min_stock' => 0, 'cost_per_unit' => 10], 500);

        $service->recordPurchase(['purchased_at' => now()->toDateString()], [['ingredient_id' => $ingredient->id, 'quantity' => 300, 'subtotal' => 3000]]);
        $service->stockOut($ingredient, StockMutationType::Damaged, 120.25, 'Basah');
        $service->adjustTo($ingredient, 600, 'Opname');

        $this->assertSame('600.000', $ingredient->fresh()->stock);
        $this->assertEqualsWithDelta(600, (float) StockMutation::where('ingredient_id', $ingredient->id)->sum('quantity'), 0.0001);
    }

    public function test_mutation_list_filters(): void
    {
        $this->actingAs($this->owner());
        $service = app(InventoryService::class);
        $milk = Ingredient::factory()->create(['name' => 'Susu Segar', 'stock' => 1000]);
        $ice = Ingredient::factory()->create(['name' => 'Es Batu', 'stock' => 1000]);

        $service->stockOut($milk, StockMutationType::Expired, 10, 'Basi pagi ini');
        $service->stockOut($ice, StockMutationType::Spilled, 10, 'Jatuh di lantai');

        $this->get('/owner/stock?type=expired')->assertOk()->assertSee('Basi pagi ini')->assertDontSee('Jatuh di lantai');
        $this->get("/owner/stock?ingredient={$ice->id}")->assertOk()->assertSee('Jatuh di lantai')->assertDontSee('Basi pagi ini');
        $this->get('/owner/stock?from=2000-01-01&to=2000-01-02')->assertOk()->assertSee('Belum ada mutasi');
        $this->get('/owner/stock?from=bukan-tanggal')->assertOk()->assertSee('Basi pagi ini');
    }

    public function test_forms_render_with_preselected_ingredient(): void
    {
        $milk = Ingredient::factory()->create(['name' => 'Susu Segar']);
        $this->actingAs($this->owner());

        $this->get("/owner/stock/out?ingredient={$milk->id}")->assertOk()->assertSee('Catat stok keluar');
        $this->get("/owner/stock/adjustment?ingredient={$milk->id}")->assertOk()->assertSee('Simpan penyesuaian');
    }

    public function test_sidebar_shows_low_stock_badge(): void
    {
        Ingredient::factory()->count(3)->create(['stock' => 0, 'min_stock' => 10]);

        $this->actingAs($this->owner())->get('/owner')->assertOk()->assertSee('sidebar-badge', false);
    }

    public function test_cashier_cannot_move_stock(): void
    {
        $milk = Ingredient::factory()->create(['stock' => 1000]);
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get('/owner/stock')->assertForbidden();
        $this->actingAs($cashier)->post('/owner/stock/out', ['ingredient_id' => $milk->id, 'type' => 'damaged', 'quantity' => 1, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($cashier)->post('/owner/stock/adjustment', ['ingredient_id' => $milk->id, 'counted_stock' => 0, 'reason' => 'x'])->assertForbidden();

        $this->assertSame('1000.000', $milk->fresh()->stock);
    }
}
