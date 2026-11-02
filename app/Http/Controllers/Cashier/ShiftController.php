<?php

namespace App\Http\Controllers\Cashier;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\OpenShiftRequest;
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

        return view('cashier.shifts.open');
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
}
