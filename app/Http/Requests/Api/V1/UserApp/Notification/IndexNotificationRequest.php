<?php

namespace App\Http\Requests\Api\V1\UserApp\Notification;

use Illuminate\Foundation\Http\FormRequest;

class IndexNotificationRequest extends FormRequest
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
            'status' => ['nullable', 'in:read,unread'],
            'type' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
