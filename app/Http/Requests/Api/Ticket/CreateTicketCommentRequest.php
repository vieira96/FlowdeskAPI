<?php

namespace App\Http\Requests\Api\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('comment', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
