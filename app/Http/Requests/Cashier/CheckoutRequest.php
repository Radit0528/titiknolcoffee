<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Validation\Rule;

class CheckoutRequest extends CartRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'client_token' => ['required', 'uuid'],
            'method' => ['nullable', Rule::in(['cash', 'qris'])],
            'cash_received' => [
                Rule::requiredIf(fn () => $this->input('method', 'cash') === 'cash'),
                'nullable',
                'numeric',
                'min:0',
                'max:999999999',
            ],
            'draft_id' => ['nullable', 'integer', Rule::exists('orders', 'id')],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'method' => 'metode pembayaran',
            'cash_received' => 'uang diterima',
        ];
    }
}
