<?php

namespace App\Http\Controllers\Owner;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\VoucherRequest;
use App\Models\Voucher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('owner.vouchers.index', [
            'vouchers' => Voucher::latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('owner.vouchers.form', [
            'voucher' => new Voucher(['type' => VoucherType::Percent, 'is_active' => true]),
            'types' => VoucherType::cases(),
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        $voucher = Voucher::create($request->voucherData());
        $this->audit->log('voucher.created', $voucher, [], ['code' => $voucher->code]);

        return redirect()->route('owner.vouchers.index')->with('success', "Voucher {$voucher->code} dibuat.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('owner.vouchers.form', ['voucher' => $voucher, 'types' => VoucherType::cases()]);
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $before = $voucher->only(['type', 'value', 'max_discount', 'quota', 'is_active']);
        $voucher->update($request->voucherData());
        $this->audit->log('voucher.updated', $voucher, $before, $voucher->only(array_keys($before)));

        return redirect()->route('owner.vouchers.index')->with('success', "Voucher {$voucher->code} diperbarui.");
    }

    /**
     * Used vouchers are kept for reporting; deactivate them instead.
     */
    public function destroy(Voucher $voucher): RedirectResponse
    {
        if ($voucher->usages()->exists()) {
            return back()->with('error', 'Voucher sudah pernah dipakai. Nonaktifkan saja agar riwayatnya tetap ada.');
        }

        $this->audit->log('voucher.deleted', $voucher, ['code' => $voucher->code]);
        $voucher->delete();

        return redirect()->route('owner.vouchers.index')->with('success', 'Voucher dihapus.');
    }
}
