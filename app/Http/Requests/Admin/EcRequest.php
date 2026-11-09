<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $ec = $this->route('ec');

        return [
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9_.-]+$/',
                Rule::unique('ecs', 'code')->ignore($ec?->id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'code', 'intitule' => 'intitulé', 'description' => 'description'];
    }
}
