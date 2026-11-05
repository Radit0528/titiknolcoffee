<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class VoidRequest extends FormRequest
{
    protected $errorBag = 'void';

    public function authorize(): bool
    {
        // Ownership of the order is checked in the controller.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'pin' => ['required', 'digits:6'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan pembatalan', 'pin' => 'PIN owner'];
    }
}
