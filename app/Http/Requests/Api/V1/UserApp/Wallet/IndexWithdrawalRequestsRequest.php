<?php

namespace App\Http\Requests\Api\V1\UserApp\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class IndexWithdrawalRequestsRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
