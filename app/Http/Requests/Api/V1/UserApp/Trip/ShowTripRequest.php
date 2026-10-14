<?php

namespace App\Http\Requests\Api\V1\UserApp\Trip;

use Illuminate\Foundation\Http\FormRequest;

class ShowTripRequest extends FormRequest
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
            // The booking's own `type`. Ride and delivery bookings are numbered separately,
            // so it says which one {id} is; without it, a ride booking is tried first.
            'type' => ['nullable', 'in:ride,ride_share,posted_ride,delivery,delivery_share'],
        ];
    }
}
