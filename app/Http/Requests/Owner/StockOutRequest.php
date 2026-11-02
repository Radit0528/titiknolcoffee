<?php

namespace App\Http\Requests\Owner;

use App\Enums\StockMutationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOutRequest extends FormRequest
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
            'ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(StockMutationType::class)->only(StockMutationType::stockOutTypes())],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['ingredient_id' => 'bahan baku', 'type' => 'jenis', 'quantity' => 'jumlah', 'reason' => 'alasan'];
    }
}
