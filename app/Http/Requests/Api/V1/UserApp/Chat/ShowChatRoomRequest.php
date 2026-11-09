<?php

namespace App\Http\Requests\Api\V1\UserApp\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ShowChatRoomRequest extends FormRequest
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
            // See ShowTripRequest -- {trip} in the chat routes is the booking id (TripPassenger
            // or DeliveryDetails), not the vehicle trip's id.
            'type' => ['nullable', 'in:ride,ride_share,posted_ride,delivery,delivery_share'],
        ];
    }
}
