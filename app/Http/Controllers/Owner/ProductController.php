<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products, private RecipeService $recipes) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $categoryId = $request->integer('category') ?: null;

        $products = Product::query()
            ->with(['category', 'recipe.items.ingredient'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $costing = $products->getCollection()->mapWithKeys(fn (Product $p) => [$p->id => $this->recipes->costing($p)]);

        return view('owner.products.index', [
            'products' => $products,
            'costing' => $costing,
            'categories' => Category::ordered()->get(),
            'search' => $search,
            'categoryId' => $categoryId,
        ]);
    }

    public function create(): View
    {
        return view('owner.products.form', [
            'product' => new Product(['is_available' => true]),
            'categories' => Category::ordered()->get(),
            'costing' => null,
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->productData(), $request->file('image'));

        return redirect()->route('owner.recipes.edit', $product)
            ->with('success', 'Produk ditambahkan. Lengkapi resepnya agar HPP dapat dihitung.');
    }

    public function edit(Product $product): View
    {
        $product->load('recipe.items.ingredient');

        return view('owner.products.form', [
            'product' => $product,
            'categories' => Category::ordered()->get(),
            'costing' => $this->recipes->costing($product),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->productData(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('owner.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_available' => ! $product->is_available]);

        return back()->with('success', $product->is_available
            ? "{$product->name} ditandai tersedia."
            : "{$product->name} ditandai habis.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('owner.products.index')->with('success', 'Produk berhasil dihapus.');
    }
}
