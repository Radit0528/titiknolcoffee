<?php

namespace App\Http\Middleware;

use App\Services\ShiftService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A cashier must open a shift before selling. The open shift is shared with
 * controllers as the `shift` request attribute.
 */
class EnsureShiftIsOpen
{
    public function __construct(private ShiftService $shifts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shift = $this->shifts->current($request->user());

        if (! $shift) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Buka shift terlebih dahulu.'], 409);
            }

            return redirect()->route('cashier.shift.create');
        }

        $request->attributes->set('shift', $shift);

        return $next($request);
    }
}
