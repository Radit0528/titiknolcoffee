<?php

namespace Tests\Feature\Owner;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_updates_and_deactivates_category(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/categories', ['name' => 'Kopi', 'sort_order' => 1, 'is_active' => '1'])
            ->assertRedirect(route('owner.categories.index'));

        $category = Category::where('name', 'Kopi')->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($owner)->put("/owner/categories/{$category->id}", ['name' => 'Kopi Panas', 'sort_order' => 2, 'is_active' => '0'])
            ->assertRedirect(route('owner.categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Kopi Panas', 'sort_order' => 2, 'is_active' => false]);
    }

    public function test_category_name_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Kopi']);

        $this->actingAs($this->owner())->post('/owner/categories', ['name' => 'Kopi', 'sort_order' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->owner())->delete("/owner/categories/{$category->id}")->assertRedirect();

        $this->assertModelMissing($category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted']);
    }

    public function test_category_with_products_cannot_be_deleted_even_if_products_are_soft_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create()->delete();

        $this->actingAs($this->owner())->delete("/owner/categories/{$category->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($category);
    }

    public function test_moving_a_category_swaps_it_with_its_neighbour(): void
    {
        $a = Category::factory()->create(['name' => 'A', 'sort_order' => 1]);
        $b = Category::factory()->create(['name' => 'B', 'sort_order' => 2]);
        $c = Category::factory()->create(['name' => 'C', 'sort_order' => 3]);

        $this->actingAs($this->owner())->post("/owner/categories/{$c->id}/move/up")->assertRedirect();

        $this->assertSame(['A', 'C', 'B'], Category::ordered()->pluck('name')->all());

        // Moving the first item up is a no-op.
        $this->actingAs($this->owner())->post("/owner/categories/{$a->id}/move/up");
        $this->assertSame(['A', 'C', 'B'], Category::ordered()->pluck('name')->all());
    }
}
