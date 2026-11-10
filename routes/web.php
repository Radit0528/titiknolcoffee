<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Cashier\DraftController;
use App\Http\Controllers\Cashier\PosController;
use App\Http\Controllers\Cashier\ShiftController;
use App\Http\Controllers\Owner\AccountController;
use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\BannerController;
use App\Http\Controllers\Owner\CategoryController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\IngredientController;
use App\Http\Controllers\Owner\ProductController;
use App\Http\Controllers\Owner\PurchaseController;
use App\Http\Controllers\Owner\RecipeController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StaffController;
use App\Http\Controllers\Owner\StockMovementController;
use App\Http\Controllers\Owner\VoucherController;
use App\Http\Controllers\Shared\OrderController;
use App\Services\ReportService;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class)->except('show');
    Route::post('categories/{category}/move/{direction}', [CategoryController::class, 'move'])
        ->whereIn('direction', ['up', 'down'])
        ->name('categories.move');

    Route::resource('products', ProductController::class)->except('show');
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

    Route::resource('ingredients', IngredientController::class)->except('show');

    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{product}', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');

    // Tahap 3 — inventory. Purchases are immutable, so no edit/delete routes.
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);

    Route::controller(StockMovementController::class)->prefix('stock')->name('stock.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('out', 'createOut')->name('out.create');
        Route::post('out', 'storeOut')->name('out.store');
        Route::get('adjustment', 'createAdjustment')->name('adjustment.create');
        Route::post('adjustment', 'storeAdjustment')->name('adjustment.store');
    });

    // Tahap 4 — POS support
    Route::resource('vouchers', VoucherController::class)->except('show');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // Tahap 7 — laporan
    Route::get('laporan', fn () => redirect()->route('owner.reports.show', 'penjualan'))->name('reports.index');
    Route::prefix('laporan/{type}')->whereIn('type', array_keys(ReportService::TYPES))->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'show'])->name('show');
        Route::get('pdf', [ReportController::class, 'pdf'])->name('pdf');
        Route::get('excel', [ReportController::class, 'excel'])->name('excel');
    });

    // Tahap 8 — staf, akun & PIN, banner, audit log
    Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
    Route::put('staff/{staff}/password', [StaffController::class, 'password'])->name('staff.password');
    Route::get('akun', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('akun/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::put('akun/pin', [AccountController::class, 'updatePin'])->name('account.pin');
    Route::resource('banners', BannerController::class)->except('show');
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
});

Route::middleware(['auth', 'role:cashier'])->prefix('kasir')->name('cashier.')->group(function () {
    Route::get('shift/buka', [ShiftController::class, 'create'])->name('shift.create');
    Route::post('shift', [ShiftController::class, 'store'])->name('shift.store');

    Route::middleware('shift.open')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('pos');
        Route::post('quote', [PosController::class, 'quote'])->name('pos.quote');
        Route::post('checkout', [PosController::class, 'checkout'])->name('pos.checkout')->middleware('throttle:60,1');

        // Tahap 6 — kas masuk/keluar & tutup shift
        Route::get('shift', [ShiftController::class, 'current'])->name('shift.current');
        Route::post('shift/kas', [ShiftController::class, 'storeCash'])->name('shift.cash');
        Route::post('shift/tutup', [ShiftController::class, 'close'])->name('shift.close');

        Route::get('draft', [DraftController::class, 'index'])->name('drafts.index');
        Route::post('draft', [DraftController::class, 'store'])->name('drafts.store');
        Route::delete('draft/{order}', [DraftController::class, 'destroy'])->name('drafts.destroy');
    });
});

// Riwayat transaksi & struk: kasir melihat miliknya sendiri, owner melihat semua.
Route::middleware(['auth', 'role:owner,cashier'])->prefix('transaksi')->name('orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('{order}', [OrderController::class, 'show'])->name('show');
    Route::get('{order}/cetak', [OrderController::class, 'print'])->name('print');
    Route::post('{order}/void', [OrderController::class, 'void'])->name('void')->middleware('throttle:20,1');
});

// Laporan shift: kasir melihat shift miliknya, owner melihat semua.
Route::middleware(['auth', 'role:owner,cashier'])->prefix('shift')->name('shifts.')->group(function () {
    Route::get('{shift}', [ShiftController::class, 'show'])->name('show');
    Route::get('{shift}/cetak', [ShiftController::class, 'print'])->name('print');
});
