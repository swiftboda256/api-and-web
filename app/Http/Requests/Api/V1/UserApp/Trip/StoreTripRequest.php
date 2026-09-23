<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use App\Models\VehicleType;
use Illuminate\Contracts\Validation\Validator;
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
            'type' => ['required', 'in:ride,delivery,ride_share,delivery_share'],
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
            'recipient_name' => ['required_if:type,delivery,delivery_share', 'string', 'max:255'],
            'recipient_phone' => ['required_if:type,delivery,delivery_share', 'string', 'max:20'],
            'package_description' => ['nullable', 'string', 'max:255'],
            'package_size' => ['nullable', 'in:small,medium,large'],
            'package_weight_kg' => ['required_if:type,delivery_share', 'nullable', 'numeric', 'min:0'],
            'requires_signature' => ['sometimes', 'boolean'],
            'seats_requested' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('vehicle_type_id')) {
                return;
            }

            if ($this->input('type') === 'ride_share') {
                $seatsRequested = (int) ($this->input('seats_requested') ?? 1);
                $capacity = VehicleType::query()->find((int) $this->input('vehicle_type_id'))?->capacity;

                if ($capacity !== null && $seatsRequested > $capacity) {
                    $validator->errors()->add('seats_requested', 'This vehicle type does not have enough seats for this request.');
                }

                return;
            }

            if ($this->input('type') === 'delivery_share' && $this->filled('package_weight_kg')) {
                $weightKg = (float) $this->input('package_weight_kg');
                $maxCargoWeightKg = VehicleType::query()->find((int) $this->input('vehicle_type_id'))?->max_cargo_weight_kg;

                if ($maxCargoWeightKg === null || (float) $maxCargoWeightKg <= 0) {
                    $validator->errors()->add('vehicle_type_id', 'This vehicle type does not support delivery pooling.');
                } elseif ($weightKg > (float) $maxCargoWeightKg) {
                    $validator->errors()->add('package_weight_kg', 'This vehicle type does not have enough cargo capacity for this package.');
                }
            }
        });
    }
}
