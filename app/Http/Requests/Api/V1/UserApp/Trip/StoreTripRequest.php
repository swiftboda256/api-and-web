<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
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
            'type' => ['required', 'in:ride,delivery'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:wallet,cash,mobile_money,card'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required_if:type,delivery', 'string', 'max:255'],
            'recipient_phone' => ['required_if:type,delivery', 'string', 'max:20'],
            'package_description' => ['nullable', 'string', 'max:255'],
            'package_size' => ['nullable', 'in:small,medium,large'],
            'package_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'requires_signature' => ['sometimes', 'boolean'],
        ];
    }
}
