<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'invoice_number' => 'nomor nota',
            'purchased_at' => 'tanggal pembelian',
            'items' => 'daftar bahan',
            'items.*.ingredient_id' => 'bahan baku',
            'items.*.quantity' => 'jumlah',
            'items.*.subtotal' => 'harga beli',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu bahan.',
            'items.*.ingredient_id.distinct' => 'Bahan yang sama dimasukkan lebih dari sekali.',
            'purchased_at.before_or_equal' => 'Tanggal pembelian tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseHeader(): array
    {
        return $this->safe()->only(['supplier_id', 'invoice_number', 'purchased_at', 'notes']);
    }

    /**
     * @return array<int, array{ingredient_id: int, quantity: string, subtotal: string}>
     */
    public function purchaseItems(): array
    {
        return array_values($this->validated('items'));
    }
}
