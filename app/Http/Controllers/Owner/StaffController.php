<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StaffPasswordRequest;
use App\Http\Requests\Owner\StaffRequest;
use App\Models\Shift;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cashier accounts. Owner accounts are not managed here.
 */
class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(): View
    {
        return view('owner.staff.index', [
            'cashiers' => User::query()
                ->where('role', UserRole::Cashier)
                ->withCount('shifts')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'openShiftUserIds' => Shift::open()->pluck('user_id')->all(),
        ]);
    }

    public function create(): View
    {
        return view('owner.staff.form', ['staff' => new User(['is_active' => true])]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $user = $this->staff->create($request->staffData());

        return redirect()->route('owner.staff.index')->with('success', "Akun kasir {$user->name} dibuat. Username: {$user->username}");
    }

    public function edit(User $staff): View
    {
        $this->ensureCashier($staff);

        return view('owner.staff.form', ['staff' => $staff]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $this->ensureCashier($staff);

        try {
            $this->staff->update($staff, $request->staffData());
        } catch (CheckoutException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('owner.staff.index')->with('success', "Akun {$staff->name} diperbarui.");
    }

    public function password(StaffPasswordRequest $request, User $staff): RedirectResponse
    {
        $this->ensureCashier($staff);
        $this->staff->resetPassword($staff, $request->validated('password'));

        return redirect()->route('owner.staff.index')->with('success', "Password {$staff->name} direset. Kasir perlu login ulang dengan password baru.");
    }

    private function ensureCashier(User $user): void
    {
        abort_unless($user->isCashier(), 404);
    }
}
