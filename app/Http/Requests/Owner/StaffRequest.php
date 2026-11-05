<?php

namespace App\Http\Requests\Owner;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create a cashier (with password) or update one (name, username, active).
 */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $staff */
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($staff)],
            'password' => $staff ? ['prohibited'] : ['required', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'username' => 'username', 'password' => 'password'];
    }

    public function messages(): array
    {
        return ['username.regex' => 'Username hanya boleh huruf kecil, angka, titik, - dan _.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffData(): array
    {
        return $this->safe()->only($this->route('staff') ? ['name', 'username', 'is_active'] : ['name', 'username', 'password']);
    }
}
