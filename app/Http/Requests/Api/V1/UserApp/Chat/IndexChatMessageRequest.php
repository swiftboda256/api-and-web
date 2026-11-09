<?php

namespace App\Http\Requests\Api\V1\UserApp\Chat;

use Illuminate\Foundation\Http\FormRequest;

class IndexChatMessageRequest extends FormRequest
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
            'type' => ['nullable', 'in:ride,ride_share,posted_ride,delivery,delivery_share'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
