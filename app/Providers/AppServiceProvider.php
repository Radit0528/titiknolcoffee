<?php

namespace App\Providers;

use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\Order;
use App\Services\ShiftService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Low-stock notification: badge next to "Bahan Baku" in the owner sidebar.
        View::composer('components.sidebar.owner', function ($view) {
            $view->with('lowStockCount', Ingredient::lowStock()->count());
        });

        // Cashier header: open shift and number of held orders.
        View::composer('layouts.cashier', function ($view) {
            $user = auth()->user();
            $view->with([
                'currentShift' => $user ? app(ShiftService::class)->current($user) : null,
                'draftCount' => $user ? Order::where('user_id', $user->id)->where('status', OrderStatus::Draft)->count() : 0,
            ]);
        });
    }
}
