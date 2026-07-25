<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletPinRequest extends FormRequest
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
            'current_pin' => ['nullable', 'digits:5'],
            'new_pin' => ['required', 'digits:5', 'confirmed'],
        ];
    }
}
