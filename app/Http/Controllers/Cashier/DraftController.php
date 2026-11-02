<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\DraftRequest;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Held orders ("tahan pesanan"). Drafts belong to the cashier who made them
 * and never reduce stock.
 */
class DraftController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    public function index(Request $request): View
    {
        return view('cashier.drafts.index', [
            'drafts' => Order::with('items')
                ->where('user_id', $request->user()->id)
                ->where('status', OrderStatus::Draft)
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function store(DraftRequest $request): JsonResponse
    {
        $draft = $request->integer('draft_id')
            ? $this->ownDraft($request, $request->integer('draft_id'))
            : null;

        try {
            $this->checkout->saveDraft($request->user(), $request->attributes->get('shift'), $request->cart(), $draft);
        } catch (CheckoutException $e) {
            return response()->json(['message' => $e->getMessage(), 'field' => $e->field], 422);
        }

        session()->flash('success', 'Pesanan disimpan sebagai draft.');

        return response()->json(['redirect' => route('cashier.pos')]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->ownDraft($request, $order->id)->delete();

        return redirect()->route('cashier.drafts.index')->with('success', 'Draft dihapus.');
    }

    private function ownDraft(Request $request, int $id): Order
    {
        return Order::whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('status', OrderStatus::Draft)
            ->firstOrFail();
    }
}
