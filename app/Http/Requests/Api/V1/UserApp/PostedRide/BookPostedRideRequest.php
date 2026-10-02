<?php

namespace App\Http\Requests\Api\V1\UserApp\PostedRide;

use Illuminate\Foundation\Http\FormRequest;

class BookPostedRideRequest extends FormRequest
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
            'seats_requested' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:wallet,cash,mobile_money,card'],
        ];
    }
}
