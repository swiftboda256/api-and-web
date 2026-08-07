<?php

namespace App\Http\Requests\Api\V1\UserApp\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'category_id' => ['nullable', 'integer', Rule::exists('support_categories', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
