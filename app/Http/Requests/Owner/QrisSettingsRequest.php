<?php

namespace App\Http\Requests\Owner;

use App\Exceptions\CheckoutException;
use App\Services\QrisService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class QrisSettingsRequest extends FormRequest
{
    protected $errorBag = 'qris';

    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Payloads copied from a scanner app sometimes carry line breaks. Spaces are
        // kept: they are part of the merchant name.
        $this->merge(['qris_static_payload' => trim(preg_replace('/[\r\n]+/', '', (string) $this->input('qris_static_payload')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qris_static_payload' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail) {
                try {
                    app(QrisService::class)->validateStatic((string) $value);
                } catch (CheckoutException $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['qris_static_payload.required' => 'Unggah gambar QRIS kedai atau tempel isi kodenya.'];
    }
}
