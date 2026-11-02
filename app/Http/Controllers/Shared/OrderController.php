<?php

namespace App\Http\Controllers\Shared;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transaction history and receipts. A cashier sees only their own orders;
 * the owner sees every order.
 */
class OrderController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $orders = Order::query()
            ->with(['user', 'payment'])
            ->withSum('items as item_count', 'quantity')
            ->where('status', '!=', OrderStatus::Draft)
            ->when($user->isCashier(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('code', 'like', '%'.$request->string('q')->trim().'%')
                    ->orWhere('customer_name', 'like', '%'.$request->string('q')->trim().'%');
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'search' => (string) $request->string('q'),
            'layout' => $user->isOwner() ? 'layouts.owner' : 'layouts.cashier',
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeView($request, $order);

        return view('orders.show', [
            'order' => $order->load(['items', 'user', 'payment']),
            'settings' => $this->settings->all(),
            'logoUrl' => $this->settings->logoUrl(),
            'layout' => $request->user()->isOwner() ? 'layouts.owner' : 'layouts.cashier',
            // The cashier lands here right after checkout: show the success banner.
            'justPaid' => $request->user()->isCashier() && $order->paid_at?->gt(now()->subMinutes(2)),
        ]);
    }

    /**
     * Bare receipt page sized for a thermal printer; prints itself on load.
     */
    public function print(Request $request, Order $order): View
    {
        $this->authorizeView($request, $order);

        $width = (int) $request->query('width', $this->settings->get('receipt_width'));

        return view('orders.print', [
            'order' => $order->load(['items', 'user', 'payment']),
            'settings' => $this->settings->all(),
            'logoUrl' => $this->settings->logoUrl(),
            'width' => in_array($width, [58, 80], true) ? $width : 58,
        ]);
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $user = $request->user();

        abort_if($order->status === OrderStatus::Draft, 404);
        abort_unless($user->isOwner() || $order->user_id === $user->id, 403, 'Transaksi ini bukan milik Anda.');
    }
}
