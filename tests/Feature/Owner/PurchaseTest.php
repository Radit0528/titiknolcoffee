<?php

namespace Tests\Feature\Owner;

use App\Enums\StockMutationType;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_adds_stock_records_mutations_and_computes_total(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::factory()->create();
        $milk = Ingredient::factory()->create(['name' => 'Susu', 'unit' => 'ml', 'stock' => 0, 'cost_per_unit' => 0]);
        $beans = Ingredient::factory()->create(['name' => 'Biji Kopi', 'unit' => 'gram', 'stock' => 0, 'cost_per_unit' => 0]);

        $response = $this->actingAs($owner)->post('/owner/purchases', [
            'supplier_id' => $supplier->id,
            'invoice_number' => 'INV-77',
            'purchased_at' => now()->toDateString(),
            'total' => 1, // ignored: the server computes the total
            'items' => [
                ['ingredient_id' => $milk->id, 'quantity' => 5000, 'subtotal' => 100000],
                ['ingredient_id' => $beans->id, 'quantity' => 1000, 'subtotal' => 250000],
            ],
        ]);

        $purchase = Purchase::sole();
        $response->assertRedirect(route('owner.purchases.show', $purchase));

        $this->assertSame('350000.00', $purchase->total);
        $this->assertSame($owner->id, $purchase->user_id);
        $this->assertMatchesRegularExpression('/^PB-\d{8}-\d{4}$/', $purchase->code);

        $this->assertSame('5000.000', $milk->fresh()->stock);
        $this->assertSame('20.0000', $milk->fresh()->cost_per_unit);
        $this->assertSame('250.0000', $beans->fresh()->cost_per_unit);

        $mutations = $purchase->mutations()->get();
        $this->assertCount(2, $mutations);
        $this->assertTrue($mutations->every(fn ($m) => $m->type === StockMutationType::Purchase));
        $this->assertSame('Pembelian '.$purchase->code, $mutations->first()->notes);
    }

    public function test_cost_becomes_weighted_average_of_old_and_new_stock(): void
    {
        // 1.000 ml on hand at Rp20 + 1.000 ml bought for Rp26.000 (Rp26/ml)
        // → (1000×20 + 1000×26) / 2000 = Rp23/ml
        $milk = Ingredient::factory()->create(['stock' => 1000, 'cost_per_unit' => 20]);

        $this->actingAs($this->owner())->post('/owner/purchases', [
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $milk->id, 'quantity' => 1000, 'subtotal' => 26000]],
        ]);

        $milk->refresh();
        $this->assertSame('2000.000', $milk->stock);
        $this->assertSame('23.0000', $milk->cost_per_unit);

        $mutation = $milk->mutations()->sole();
        $this->assertSame('26.0000', $mutation->cost_per_unit, 'the ledger keeps the price actually paid');
    }

    public function test_purchase_validation(): void
    {
        $ingredient = Ingredient::factory()->create();
        $deleted = Ingredient::factory()->create();
        $deleted->delete();

        $this->actingAs($this->owner())->post('/owner/purchases', [
            'purchased_at' => now()->addDay()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 0, 'subtotal' => -5],
                ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'subtotal' => 100],
                ['ingredient_id' => $deleted->id, 'quantity' => 10, 'subtotal' => 100],
            ],
        ])->assertSessionHasErrors([
            'purchased_at', 'items.0.quantity', 'items.0.subtotal', 'items.0.ingredient_id', 'items.2.ingredient_id',
        ]);

        $this->actingAs($this->owner())->post('/owner/purchases', ['purchased_at' => now()->toDateString(), 'items' => []])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame('1000.000', $ingredient->fresh()->stock);
    }

    public function test_purchase_list_filters_and_totals_period(): void
    {
        $service = app(InventoryService::class);
        $a = Ingredient::factory()->create();
        $this->actingAs($this->owner());

        $service->recordPurchase(['purchased_at' => '2026-10-05'], [['ingredient_id' => $a->id, 'quantity' => 10, 'subtotal' => 10000]]);
        $service->recordPurchase(['purchased_at' => '2026-10-20'], [['ingredient_id' => $a->id, 'quantity' => 10, 'subtotal' => 30000]]);

        $this->get('/owner/purchases?from=2026-10-10&to=2026-10-31')
            ->assertOk()
            ->assertSee('PB-20261020')
            ->assertDontSee('PB-20261005')
            ->assertSee('Rp30.000');
    }

    public function test_purchase_detail_and_create_page_render(): void
    {
        $ingredient = Ingredient::factory()->create(['name' => 'Gula Aren']);
        $this->actingAs($this->owner());
        $purchase = app(InventoryService::class)->recordPurchase(
            ['purchased_at' => now()->toDateString(), 'invoice_number' => 'NOTA-1'],
            [['ingredient_id' => $ingredient->id, 'quantity' => 2000, 'subtotal' => 60000]],
        );

        $this->get("/owner/purchases/{$purchase->id}")->assertOk()->assertSee('Gula Aren')->assertSee('NOTA-1')->assertSee('Rp60.000');
        $this->get("/owner/purchases/create?ingredient={$ingredient->id}")->assertOk()->assertSee('Bahan yang dibeli');
    }

    public function test_purchases_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->owner());
        $purchase = app(InventoryService::class)->recordPurchase(
            ['purchased_at' => now()->toDateString()],
            [['ingredient_id' => Ingredient::factory()->create()->id, 'quantity' => 1, 'subtotal' => 1]],
        );

        $this->delete("/owner/purchases/{$purchase->id}")->assertMethodNotAllowed();
        $this->put("/owner/purchases/{$purchase->id}", [])->assertMethodNotAllowed();
    }

    public function test_cashier_cannot_record_purchases(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($this->cashier())->post('/owner/purchases', [
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 1000, 'subtotal' => 1]],
        ])->assertForbidden();

        $this->assertSame('1000.000', $ingredient->fresh()->stock);
    }
}
