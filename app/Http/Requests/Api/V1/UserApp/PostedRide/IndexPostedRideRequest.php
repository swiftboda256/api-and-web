<?php

namespace App\Http\Requests\Api\V1\UserApp\PostedRide;

use Illuminate\Foundation\Http\FormRequest;

class IndexPostedRideRequest extends FormRequest
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
            'origin_latitude' => ['nullable', 'required_with:origin_longitude', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'required_with:origin_latitude', 'numeric', 'between:-180,180'],
            'destination_latitude' => ['nullable', 'required_with:destination_longitude', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'required_with:destination_latitude', 'numeric', 'between:-180,180'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
