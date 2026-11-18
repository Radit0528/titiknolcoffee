<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuVideo;
use App\Services\ProductService;
use App\Services\SettingService;
use Illuminate\View\View;

/**
 * Public landing page for customers: autoplaying promo videos, promo banners,
 * the menu with prices, and shop info. Read-only; ordering stays with the cashier.
 */
class HomeController extends Controller
{
    public function __construct(
        private ProductService $products,
        private SettingService $settings,
    ) {}

    public function __invoke(): View
    {
        $categories = Category::active()->ordered()
            ->with(['products' => fn ($q) => $q->orderBy('name')->with('recipe.items.ingredient')])
            ->get()
            ->filter(fn (Category $c) => $c->products->isNotEmpty())
            ->values();

        // Sold out = switched off by the owner, or not enough stock for one serving.
        $soldOut = $categories->flatMap->products
            ->filter(fn ($p) => ! $p->is_available || $this->products->servingsAvailable($p) === 0)
            ->pluck('id')
            ->all();

        return view('public.home', [
            'categories' => $categories,
            // Only the videos on screen play (see resources/js/public/home.js).
            'videos' => MenuVideo::showing()->limit(30)->get(),
            'soldOut' => $soldOut,
            'banners' => Banner::showing()->limit(6)->get(),
            'settings' => $this->settings->all(),
            'logoUrl' => $this->settings->logoUrl(),
        ]);
    }
}
