<?php

namespace App\Http\Requests\Api\V1\UserApp\PromoCode;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPromoCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
        ];
    }
}
