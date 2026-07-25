<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class IndexWalletHistoryRequest extends FormRequest
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
            'type' => ['nullable', 'in:topup,trip_payment,trip_payout,refund,withdrawal,promo_credit,adjustment'],
            'direction' => ['nullable', 'in:credit,debit'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
