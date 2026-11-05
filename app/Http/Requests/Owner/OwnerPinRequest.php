<?php

namespace App\Http\Requests\Owner;

use App\Services\OwnerPinService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerPinRequest extends FormRequest
{
    protected $errorBag = 'pin';

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
            // Asking for the password stops someone at an unlocked owner screen from changing the PIN.
            'pin_current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits:6', 'confirmed', Rule::notIn(OwnerPinService::WEAK_PINS)],
        ];
    }

    public function attributes(): array
    {
        return ['pin_current_password' => 'password', 'pin' => 'PIN'];
    }

    public function messages(): array
    {
        return ['pin.not_in' => 'PIN terlalu mudah ditebak. Hindari angka berulang atau berurutan.'];
    }
}
