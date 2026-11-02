<?php

namespace App\Http\Requests\Cashier;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cart shape shared by checkout, draft and voucher-check requests. Only ids,
 * quantities and notes are accepted; prices are always read from the database.
 */
class CartRequest extends FormRequest
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
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'voucher_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'keranjang',
            'items.*.quantity' => 'jumlah',
            'items.*.note' => 'catatan',
            'order_type' => 'jenis pesanan',
            'customer_name' => 'nama pelanggan',
            'voucher_code' => 'kode voucher',
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Keranjang masih kosong.', 'items.min' => 'Keranjang masih kosong.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function cart(): array
    {
        $data = $this->validated();
        $data['items'] = array_values($data['items']);

        return $data;
    }
}
