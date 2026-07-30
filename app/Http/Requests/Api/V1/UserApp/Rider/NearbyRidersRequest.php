<?php

namespace App\Http\Requests\Api\V1\UserApp\Rider;

use Illuminate\Foundation\Http\FormRequest;

class NearbyRidersRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ];
    }
}
