<?php

namespace App\Http\Requests\Api\Ticket;

use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'category_id' => ['sometimes', 'uuid', 'exists:team_categories,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
