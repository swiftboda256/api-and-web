<?php

namespace App\Http\Requests\Api\V1\RiderApp\Ride;

use Illuminate\Foundation\Http\FormRequest;

class PostRideRequest extends FormRequest
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
            'origin_latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['required', 'numeric', 'between:-180,180'],
            'origin_address' => ['nullable', 'string', 'max:255'],
            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'departs_at' => ['required', 'date', 'after:now'],
            'seat_fare' => ['required', 'numeric', 'gt:0'],
            'available_seats' => ['nullable', 'integer', 'min:1'],
            'distance_km' => ['required', 'numeric', 'min:0'],
        ];
    }
}
