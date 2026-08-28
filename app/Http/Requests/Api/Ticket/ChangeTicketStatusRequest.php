<?php

namespace App\Http\Requests\Api\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateStatus', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['resolved', 'closed'])],
        ];
    }
}
