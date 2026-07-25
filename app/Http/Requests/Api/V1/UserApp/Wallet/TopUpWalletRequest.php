<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class TopUpWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:mobile_money,card'],
            'phone' => ['required_if:method,mobile_money', 'string'],
        ];
    }
}
