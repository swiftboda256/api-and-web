<?php

namespace App\Http\Requests\Api\V1\UserApp\Notification;

use Illuminate\Foundation\Http\FormRequest;

class MarkAsReadNotificationRequest extends FormRequest
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
            'ids' => ['required_without:mark_all', 'nullable', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'mark_all' => ['required_without:ids', 'nullable', 'boolean'],
        ];
    }
}
