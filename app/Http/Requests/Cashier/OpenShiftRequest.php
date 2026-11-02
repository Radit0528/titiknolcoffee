<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCashier() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['opening_cash' => ['required', 'integer', 'min:0', 'max:999999999']];
    }

    public function attributes(): array
    {
        return ['opening_cash' => 'modal awal'];
    }
}
