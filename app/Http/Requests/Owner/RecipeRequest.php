<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecipeRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['array', 'max:50'],
            'items.*.ingredient_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('ingredients', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.ingredient_id' => 'bahan baku',
            'items.*.quantity' => 'takaran',
        ];
    }

    public function messages(): array
    {
        return ['items.*.ingredient_id.distinct' => 'Bahan baku yang sama dimasukkan lebih dari sekali.'];
    }

    /**
     * @return array<int, array{ingredient_id: int, quantity: string}>
     */
    public function recipeItems(): array
    {
        return array_values($this->validated('items', []));
    }
}
