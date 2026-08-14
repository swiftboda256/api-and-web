<?php

namespace App\Http\Requests\Api\V1\RiderApp\Ride;

use Illuminate\Foundation\Http\FormRequest;

class EndRideRequest extends FormRequest
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
            'proof_of_delivery_photo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
