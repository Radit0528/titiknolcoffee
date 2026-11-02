<?php

namespace Tests\Feature\Owner;

use App\Models\Ingredient;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_supplier(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/suppliers', [
            'name' => 'Sangrai Banyumas', 'phone' => '0812-3456-7890', 'email' => 'halo@sangrai.test', 'address' => 'Purwokerto',
        ])->assertRedirect(route('owner.suppliers.index'));

        $supplier = Supplier::where('name', 'Sangrai Banyumas')->firstOrFail();

        $this->actingAs($owner)->put("/owner/suppliers/{$supplier->id}", [
            'name' => 'Sangrai Banyumas Roastery', 'phone' => '', 'email' => '', 'address' => '',
        ])
            ->assertRedirect(route('owner.suppliers.index'));

        $this->assertSame('Sangrai Banyumas Roastery', $supplier->fresh()->name);
        $this->assertNull($supplier->fresh()->phone);
    }

    public function test_invalid_contact_is_rejected(): void
    {
        $this->actingAs($this->owner())->post('/owner/suppliers', ['name' => 'X', 'phone' => 'abc', 'email' => 'bukan-email'])
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_deleting_supplier_keeps_ingredient_link_for_history(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Toko Lama']);
        $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->owner())->delete("/owner/suppliers/{$supplier->id}");

        $this->assertSoftDeleted($supplier);
        $this->assertSame('Toko Lama', $ingredient->fresh()->supplier->name);
    }
}
