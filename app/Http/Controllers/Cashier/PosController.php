<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CartRequest;
use App\Http\Requests\Cashier\CheckoutRequest;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\ProductService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private ProductService $products,
        private SettingService $settings,
    ) {}

    /**
     * POS screen. `?draft=ID` loads a held order back into the cart.
     */
    public function index(Request $request): View
    {
        $categories = Category::active()->ordered()
            ->with(['products' => fn ($q) => $q->orderBy('name')->with('recipe.items.ingredient')])
            ->get()
            ->filter(fn (Category $c) => $c->products->isNotEmpty())
            ->values();

        $catalog = $categories->flatMap(fn (Category $category) => $category->products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (int) round((float) $p->price),
            'category_id' => $category->id,
            'category' => $category->name,
            'image' => $p->imageUrl(),
            'available' => $p->is_available,
            // null = no recipe (no stock limit); otherwise servings the stock allows.
            'servings' => $this->products->servingsAvailable($p),
        ]))->values();

        $draft = $request->integer('draft')
            ? Order::with('items')->whereKey($request->integer('draft'))
                ->where('user_id', $request->user()->id)
                ->where('status', OrderStatus::Draft)
                ->first()
            : null;

        return view('cashier.pos.index', [
            'categories' => $categories,
            'catalog' => $catalog,
            'banners' => Banner::showing()->limit(6)->get(),
            'draft' => $draft ? [
                'id' => $draft->id,
                'order_type' => $draft->order_type->value,
                'customer_name' => $draft->customer_name,
                'voucher_code' => $draft->voucher_code,
                'items' => $draft->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity, 'note' => $i->note])->values(),
            ] : null,
            'config' => [
                'taxRate' => $this->settings->taxRate(),
                'serviceRate' => $this->settings->serviceChargeRate(),
                'cashRounding' => $this->settings->cashRounding(),
                'clientToken' => (string) Str::uuid(),
                'urls' => [
                    'quote' => route('cashier.pos.quote'),
                    'checkout' => route('cashier.pos.checkout'),
                    'draft' => route('cashier.drafts.store'),
                    'drafts' => route('cashier.drafts.index'),
                ],
            ],
            'orderTypes' => OrderType::cases(),
            'draftCount' => Order::where('user_id', $request->user()->id)->where('status', OrderStatus::Draft)->count(),
        ]);
    }

    /**
     * Server-side price check, used when a voucher is applied.
     */
    public function quote(CartRequest $request): JsonResponse
    {
        $cart = $request->cart();

        try {
            $priced = $this->checkout->price($cart['items'], $cart['voucher_code'] ?? null);
        } catch (CheckoutException $e) {
            return response()->json(['message' => $e->getMessage(), 'field' => $e->field], 422);
        }

        return response()->json([
            'voucher' => $priced['voucher'] ? ['code' => $priced['voucher']->code, 'name' => $priced['voucher']->name] : null,
            'subtotal' => $priced['subtotal'],
            'discount' => $priced['discount'],
            'service_charge' => $priced['service_charge'],
            'tax' => $priced['tax'],
            'total' => $priced['total'],
            'cash_total' => $priced['total'] + $this->checkout->cashRounding($priced['total']),
        ]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $order = $this->checkout->checkoutCash($request->user(), $request->attributes->get('shift'), $request->cart());
        } catch (CheckoutException $e) {
            return response()->json(['message' => $e->getMessage(), 'field' => $e->field], 422);
        }

        return response()->json([
            'order' => $order->code,
            'redirect' => route('orders.show', $order),
        ]);
    }
}
