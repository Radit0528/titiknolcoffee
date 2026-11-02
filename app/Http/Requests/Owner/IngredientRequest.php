<?php

namespace App\Http\Requests\Owner;

use App\Enums\IngredientUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by create and update. `initial_stock` is only accepted on create;
 * afterwards stock moves through stock in/out (Tahap 3) so it stays traceable.
 */
class IngredientRequest extends FormRequest
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
        $creating = $this->route('ingredient') === null;

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('ingredients', 'name')->ignore($this->route('ingredient'))->whereNull('deleted_at'),
            ],
            'unit' => [$creating ? 'required' : 'prohibited', Rule::enum(IngredientUnit::class)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'initial_stock' => [$creating ? 'required' : 'prohibited', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama bahan',
            'unit' => 'satuan',
            'supplier_id' => 'supplier',
            'min_stock' => 'minimum stok',
            'cost_per_unit' => 'harga modal per satuan',
            'initial_stock' => 'stok awal',
        ];
    }

    public function messages(): array
    {
        return ['unit.prohibited' => 'Satuan tidak dapat diubah setelah bahan dibuat karena takaran resep bergantung padanya.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function ingredientData(): array
    {
        return $this->safe()->except('initial_stock');
    }
}
