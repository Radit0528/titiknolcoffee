<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AccountPasswordRequest;
use App\Http\Requests\Owner\OwnerPinRequest;
use App\Services\AuditLogger;
use App\Services\OwnerPinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The owner's own password and approval PIN.
 */
class AccountController extends Controller
{
    public function __construct(
        private OwnerPinService $pins,
        private AuditLogger $audit,
    ) {}

    public function edit(Request $request): View
    {
        return view('owner.account.edit', [
            'user' => $request->user(),
            'hasPin' => $request->user()->pin !== null,
        ]);
    }

    public function updatePassword(AccountPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Sign out other devices that still hold a session from before the change.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $this->audit->log('owner.password_changed', $user);

        return back()->with('success', 'Password berhasil diubah.');
    }

    public function updatePin(OwnerPinRequest $request): RedirectResponse
    {
        $this->pins->set($request->user(), $request->validated('pin'));

        return back()->with('success', 'PIN owner disimpan. Pakai PIN ini untuk menyetujui pembatalan transaksi.');
    }
}
