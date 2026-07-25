<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletRequest extends FormRequest
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
            'pin' => ['required', 'digits:5', 'confirmed'],
        ];
    }
}
