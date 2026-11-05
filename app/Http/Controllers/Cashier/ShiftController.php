<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\CashMovementType;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CashMovementRequest;
use App\Http\Requests\Cashier\CloseShiftRequest;
use App\Http\Requests\Cashier\OpenShiftRequest;
use App\Models\Shift;
use App\Services\SettingService;
use App\Services\ShiftService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->shifts->current($request->user())) {
            return redirect()->route('cashier.pos');
        }

        return view('cashier.shifts.open', [
            'recentShifts' => Shift::where('user_id', $request->user()->id)->whereNotNull('closed_at')->latest('id')->limit(5)->get(),
        ]);
    }

    public function store(OpenShiftRequest $request): RedirectResponse
    {
        try {
            $shift = $this->shifts->open($request->user(), $request->integer('opening_cash'));
        } catch (CheckoutException $e) {
            return redirect()->route('cashier.pos')->with('error', $e->getMessage());
        }

        return redirect()->route('cashier.pos')
            ->with('success', 'Shift dibuka dengan modal awal '.Format::rupiah($shift->opening_cash).'.');
    }

    /**
     * Current shift: running totals, cash in/out and the close form.
     */
    public function current(Request $request): View
    {
        $shift = $request->attributes->get('shift');

        return view('cashier.shifts.current', [
            'shift' => $shift,
            'summary' => $this->shifts->summary($shift),
            'movements' => $shift->cashMovements()->latest('id')->get(),
            'types' => CashMovementType::cases(),
        ]);
    }

    public function storeCash(CashMovementRequest $request): RedirectResponse
    {
        $type = CashMovementType::from($request->validated('type'));

        try {
            $this->shifts->recordCash($request->attributes->get('shift'), $request->user(), $type, $request->integer('amount'), $request->validated('reason'));
        } catch (CheckoutException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('cashier.shift.current')
            ->with('success', $type->label().' '.Format::rupiah($request->integer('amount')).' dicatat.');
    }

    public function close(CloseShiftRequest $request): RedirectResponse
    {
        try {
            $shift = $this->shifts->close($request->attributes->get('shift'), $request->integer('closing_cash'), $request->validated('closing_notes'));
        } catch (CheckoutException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('shifts.show', $shift)->with('success', 'Shift ditutup.');
    }

    /**
     * Report of one shift (open or closed). Cashier: own shifts; owner: all.
     */
    public function show(Request $request, Shift $shift, SettingService $settings): View
    {
        $this->authorizeView($request, $shift);

        return view('cashier.shifts.show', [
            'shift' => $shift->load('user'),
            'summary' => $this->shifts->summary($shift),
            'movements' => $shift->cashMovements()->with('user')->oldest('id')->get(),
            'layout' => $request->user()->isOwner() ? 'layouts.owner' : 'layouts.cashier',
            'storeName' => $settings->get('store_name'),
        ]);
    }

    /**
     * Thermal-printer version of the shift report.
     */
    public function print(Request $request, Shift $shift, SettingService $settings): View
    {
        $this->authorizeView($request, $shift);

        return view('cashier.shifts.print', [
            'shift' => $shift->load('user'),
            'summary' => $this->shifts->summary($shift),
            'movements' => $shift->cashMovements()->oldest('id')->get(),
            'storeName' => $settings->get('store_name'),
            'width' => (int) $settings->get('receipt_width') === 80 ? 80 : 58,
        ]);
    }

    private function authorizeView(Request $request, Shift $shift): void
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $shift->user_id === $user->id, 403, 'Shift ini bukan milik Anda.');
    }
}
