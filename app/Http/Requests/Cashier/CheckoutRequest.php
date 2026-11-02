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
            'cash_received' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'draft_id' => ['nullable', 'integer', Rule::exists('orders', 'id')],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['cash_received' => 'uang diterima'];
    }
}
