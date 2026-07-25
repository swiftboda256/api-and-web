<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:1'],
            'channel' => ['required', 'in:mobile_money,bank_account'],
            'provider' => ['required', 'string', 'max:255'],
            'account_identifier' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'digits:5'],
        ];
    }
}
