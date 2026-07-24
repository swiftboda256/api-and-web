<?php

namespace App\Http\Requests\Api\V1\UserApp\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'other_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/', Rule::unique('users', 'phone')->ignore($this->user()?->id)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
