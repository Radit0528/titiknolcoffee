<?php

namespace App\Http\Requests\Owner;

use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $percent = $this->input('type') === VoucherType::Percent->value;

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($this->route('voucher'))],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'value' => ['required', 'numeric', 'gt:0', $percent ? 'max:100' : 'max:999999999'],
            'min_purchase' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode voucher',
            'name' => 'nama voucher',
            'type' => 'jenis diskon',
            'value' => 'nilai diskon',
            'min_purchase' => 'minimum pembelian',
            'max_discount' => 'maksimum diskon',
            'quota' => 'kuota',
            'starts_at' => 'tanggal mulai',
            'ends_at' => 'tanggal berakhir',
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Kode voucher hanya boleh huruf, angka, - dan _.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function voucherData(): array
    {
        $data = $this->validated();
        $data['min_purchase'] ??= 0;

        return $data;
    }
}
