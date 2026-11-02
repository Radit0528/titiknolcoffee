<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): Product
    {
        if ($image) {
            $data['image_path'] = $image->store('products', 'public');
        }

        return Product::create($data);
    }

    /**
     * Update a product. Price changes are written to the audit log; a new
     * image replaces (and deletes) the old file.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        $oldImage = $product->image_path;

        if ($image) {
            $data['image_path'] = $image->store('products', 'public');
        } elseif ($removeImage) {
            $data['image_path'] = null;
        }

        DB::transaction(function () use ($product, $data) {
            $oldPrice = (string) $product->price;
            $product->update($data);

            if (round((float) $oldPrice, 2) !== round((float) $product->price, 2)) {
                $this->audit->log('product.price_changed', $product, ['price' => $oldPrice], ['price' => (string) $product->price]);
            }
        });

        if ($oldImage && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $product;
    }

    /**
     * Soft delete keeps the row (and its image) so past orders still resolve.
     */
    public function delete(Product $product): void
    {
        $this->audit->log('product.deleted', $product, ['name' => $product->name, 'price' => (string) $product->price]);
        $product->delete();
    }

    /**
     * How many servings current stock allows, or null when the product has no
     * recipe (nothing to run out of). Expects recipe.items.ingredient loaded.
     */
    public function servingsAvailable(Product $product): ?int
    {
        $items = $product->recipe?->items ?? collect();

        if ($items->isEmpty()) {
            return null;
        }

        return (int) $items->min(fn ($item) => (float) $item->quantity > 0
            ? floor(max(0, (float) $item->ingredient->stock) / (float) $item->quantity)
            : PHP_INT_MAX);
    }
}
