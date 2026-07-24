<?php

namespace App\Http\Requests\Api\V1\UserApp\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'code' => ['required', 'digits:5'],
        ];
    }
}
