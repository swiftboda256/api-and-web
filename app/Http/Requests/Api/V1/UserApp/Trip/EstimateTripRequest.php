<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class EstimateTripRequest extends FormRequest
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
            'dropoff_latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['required', 'numeric', 'between:-180,180'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
