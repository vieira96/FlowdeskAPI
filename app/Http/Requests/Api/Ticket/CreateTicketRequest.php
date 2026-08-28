<?php

namespace App\Http\Requests\Api\Ticket;

use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['required', 'uuid', 'exists:team_categories,id'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ];
    }
}
