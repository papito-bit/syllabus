<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutorisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('manageAutorisations', $this->route('syllabus'));
    }

    public function rules(): array
    {
        return [
            // identifiant public (ULID) de l'enseignant, jamais l'id interne
            'user' => ['required', 'string', 'size:26', Rule::exists('users', 'public_id')],
            'est_responsable' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user' => 'enseignant',
            'est_responsable' => 'enseignant responsable',
        ];
    }
}
