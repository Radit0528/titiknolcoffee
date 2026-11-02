<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('owner.categories.index', [
            'categories' => Category::ordered()->withCount('products')->get(),
        ]);
    }

    public function create(): View
    {
        return view('owner.categories.form', [
            'category' => new Category(['sort_order' => (Category::max('sort_order') ?? 0) + 1, 'is_active' => true]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('owner.categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('owner.categories.form', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('owner.categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    /**
     * Categories still holding products (including soft-deleted ones, which
     * past orders reference) cannot be removed — deactivate them instead.
     */
    public function destroy(Category $category, AuditLogger $audit): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->with('error', 'Kategori masih memiliki produk. Pindahkan produknya atau nonaktifkan kategori ini.');
        }

        $audit->log('category.deleted', $category, ['name' => $category->name]);
        $category->delete();

        return redirect()->route('owner.categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    /**
     * Swap a category with its neighbour above or below, then renumber
     * all categories 1..n so the order stays gap-free.
     */
    public function move(Category $category, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($category, $direction) {
            $ordered = Category::ordered()->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (Category $c) => $c->is($category));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach ($items as $position => $item) {
                $item->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }
}
