<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Validation\Rule;

class DraftRequest extends CartRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'draft_id' => ['nullable', 'integer', Rule::exists('orders', 'id')],
        ];
    }
}
