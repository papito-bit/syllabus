<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSyllabusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->estAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'ec' => ['required', 'string', 'size:26', Rule::exists('ecs', 'public_id')],
            'annee' => ['required', 'string', 'size:26', Rule::exists('annees_universitaires', 'public_id')],
        ];
    }

    public function attributes(): array
    {
        return ['ec' => 'EC', 'annee' => 'année universitaire'];
    }
}
