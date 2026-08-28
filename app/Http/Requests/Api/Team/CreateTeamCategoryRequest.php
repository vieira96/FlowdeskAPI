<?php

namespace App\Http\Requests\Api\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTeamCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('team_categories', 'name')->where('team_id', $this->input('team_id')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
