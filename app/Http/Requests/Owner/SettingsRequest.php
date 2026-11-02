<?php

namespace App\Http\Requests\Owner;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:100'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'receipt_width' => ['required', Rule::in([58, 80])],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'cash_rounding' => ['required', Rule::in(SettingService::ROUNDING_OPTIONS)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['remove_logo' => $this->boolean('remove_logo')]);
    }

    public function attributes(): array
    {
        return [
            'store_name' => 'nama toko',
            'store_address' => 'alamat',
            'store_phone' => 'nomor telepon',
            'receipt_footer' => 'footer struk',
            'receipt_width' => 'ukuran struk',
            'tax_percent' => 'pajak',
            'service_charge_percent' => 'service charge',
            'cash_rounding' => 'pembulatan tunai',
            'logo' => 'logo',
        ];
    }
}
