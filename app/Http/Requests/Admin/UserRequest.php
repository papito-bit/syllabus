<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $cible = $this->route('user');
        $creation = $this->isMethod('POST');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($cible?->id),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['sometimes', 'boolean'],
            // À la création : mot de passe facultatif (sinon un mot de passe temporaire est généré).
            // Jamais modifiable ici ensuite : on passe par la réinitialisation.
            'password' => $creation
                ? ['sometimes', 'nullable', 'string', Password::min(10)->mixedCase()->numbers()]
                : ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'role' => 'rôle',
            'is_active' => 'compte actif',
            'password' => 'mot de passe',
        ];
    }
}
