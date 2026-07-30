<?php

namespace App\Http\Requests\Api\V1\UserApp\Device;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
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
            'fcm_token' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
