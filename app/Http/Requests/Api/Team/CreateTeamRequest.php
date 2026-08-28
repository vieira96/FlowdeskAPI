<?php

namespace App\Http\Requests\Api\Team;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:teams,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ];
    }
}
