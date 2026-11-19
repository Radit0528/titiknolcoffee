<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\QrisSettingsRequest;
use App\Services\QrisService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;

/**
 * The shop's static QRIS sticker, used for the "QRIS Kedai" payment method.
 */
class QrisSettingsController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private QrisService $qris,
    ) {}

    public function update(QrisSettingsRequest $request): RedirectResponse
    {
        $payload = $request->validated('qris_static_payload');
        $info = $this->qris->validateStatic($payload);
        $this->settings->update(['qris_static_payload' => $payload]);

        return redirect()->route('owner.settings.edit')
            ->with('success', "QRIS Kedai disimpan: {$info['merchant']}. Kasir sekarang bisa memilih metode QRIS Kedai.");
    }

    public function destroy(): RedirectResponse
    {
        $this->settings->update(['qris_static_payload' => '']);

        return redirect()->route('owner.settings.edit')->with('success', 'QRIS Kedai dinonaktifkan.');
    }
}
