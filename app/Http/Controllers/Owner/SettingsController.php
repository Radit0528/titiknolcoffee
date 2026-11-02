<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function edit(): View
    {
        return view('owner.settings.edit', [
            'settings' => $this->settings->all(),
            'logoUrl' => $this->settings->logoUrl(),
            'roundingOptions' => SettingService::ROUNDING_OPTIONS,
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $values = $request->safe()->except(['logo', 'remove_logo']);
        $oldLogo = $this->settings->get('store_logo');

        if ($request->hasFile('logo')) {
            $values['store_logo'] = $request->file('logo')->store('settings', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $values['store_logo'] = null;
        }

        $this->settings->update($values);

        if (array_key_exists('store_logo', $values) && $oldLogo && $oldLogo !== $values['store_logo']) {
            Storage::disk('public')->delete($oldLogo);
        }

        return redirect()->route('owner.settings.edit')->with('success', 'Pengaturan toko disimpan.');
    }
}
